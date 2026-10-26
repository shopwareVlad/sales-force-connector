<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\MessageQueue\Handler;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncCustomerMessage;
use ItDe\SalesforceConnector\Service\ConfigProvider;
use ItDe\SalesforceConnector\Service\CustomerContactMapper;
use ItDe\SalesforceConnector\Service\SalesforceApi;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class SyncCustomerMessageHandler
{
    public function __construct(
        private EntityRepository    $customerRepository,
        private EntityRepository    $syncLogRepository,
        private SalesforceApi       $salesforceApi,
        private CustomerContactMapper $contactMapper,
        private ConfigProvider      $configProvider,
        private LoggerInterface     $logger,
    ) {
    }

    public function __invoke(SyncCustomerMessage $message): void
    {
        $startMs  = (int) round(microtime(true) * 1000);
        $context  = $message->context;
        $customer = $this->loadCustomer($message->customerId, $context);

        if ($customer === null) {
            return;
        }

        $syncContext = clone $context;
        $syncContext->addState('salesforce-sync-update');

        try {
            $sfId = $this->syncContact($customer);
            $durationMs = (int) round(microtime(true) * 1000) - $startMs;

            $this->persistSyncResult($customer, $syncContext, 'success', null, $sfId);
            $this->writeSyncLog($customer->getId(), 'success', $sfId, null, $durationMs, $context);

        } catch (\Throwable $e) {
            $durationMs = (int) round(microtime(true) * 1000) - $startMs;
            $error      = $this->humanizeError($e->getMessage());

            $this->logger->error('Salesforce customer sync failed', [
                'customerId' => $customer->getId(),
                'error'      => $error,
            ]);

            $this->persistSyncResult($customer, $syncContext, 'error', $error);
            $this->writeSyncLog($customer->getId(), 'error', null, $error, $durationMs, $context);
        }
    }

    private function loadCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = (new Criteria([$customerId]))
            ->addAssociation('defaultBillingAddress')
            ->addAssociation('defaultBillingAddress.country')
            ->addAssociation('defaultBillingAddress.countryState');

        return $this->customerRepository->search($criteria, $context)->first();
    }

    private function syncContact(CustomerEntity $customer): ?string
    {
        $salesChannelId = $customer->getSalesChannelId();

        if ($this->configProvider->getConflictResolution($salesChannelId) === 'salesforce_wins') {
            try {
                $existing = $this->salesforceApi->getByExternalId(
                    'Contact', 'Shopware_Customer_ID__c', $customer->getId(), ['Id'], $salesChannelId
                );
                if (!empty($existing['Id'])) {
                    return $existing['Id'];
                }
            } catch (\Throwable) {
            }
        }

        $payload = $this->contactMapper->toPayload($customer, $salesChannelId);

        try {
            $this->salesforceApi->upsertByExternalId(
                'Contact',
                'Shopware_Customer_ID__c',
                $customer->getId(),
                $payload,
                $salesChannelId
            );
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();

            if ($this->isRestrictedPicklistError($msg, ['MailingCountryCode', 'MailingState'])) {
                $this->logger->warning('Salesforce country/state picklist mismatch — syncing without address region fields', [
                    'customerId'      => $customer->getId(),
                    'countryIso'      => $customer->getDefaultBillingAddress()?->getCountry()?->getIso(),
                    'originalError'   => $msg,
                ]);

                $fallbackPayload = array_diff_key($payload, array_flip(['MailingCountryCode', 'MailingState']));

                $this->salesforceApi->upsertByExternalId(
                    'Contact',
                    'Shopware_Customer_ID__c',
                    $customer->getId(),
                    $fallbackPayload,
                    $salesChannelId
                );

            } elseif ($this->isFieldIntegrityError($msg, 'MailingState')) {
                $this->logger->warning('Salesforce MailingState rejected — syncing without state field', [
                    'customerId'    => $customer->getId(),
                    'originalError' => $msg,
                ]);

                $fallbackPayload = array_diff_key($payload, array_flip(['MailingState']));

                $this->salesforceApi->upsertByExternalId(
                    'Contact',
                    'Shopware_Customer_ID__c',
                    $customer->getId(),
                    $fallbackPayload,
                    $salesChannelId
                );

            } elseif (str_contains($msg, 'DUPLICATES_DETECTED')) {
                $existingId = $this->extractDuplicateContactId($msg);
                if ($existingId !== null) {
                    $this->logger->warning('Salesforce duplicate Contact detected — linking Shopware customer to existing SF record', [
                        'customerId'          => $customer->getId(),
                        'salesforceContactId' => $existingId,
                    ]);
                    return $existingId;
                }
                throw $e;

            } else {
                throw $e;
            }
        }

        return $this->fetchSalesforceContactId($customer->getId(), $salesChannelId);
    }

    private function isRestrictedPicklistError(string $errorMessage, array $fields): bool
    {
        if (!str_contains($errorMessage, 'INVALID_OR_NULL_FOR_RESTRICTED_PICKLIST')) {
            return false;
        }

        foreach ($fields as $field) {
            if (str_contains($errorMessage, $field)) {
                return true;
            }
        }

        return false;
    }

    private function isFieldIntegrityError(string $errorMessage, string $field): bool
    {
        return str_contains($errorMessage, 'FIELD_INTEGRITY_EXCEPTION')
            && str_contains($errorMessage, $field);
    }

    private function extractDuplicateContactId(string $errorMessage): ?string
    {
        $jsonStart = strpos($errorMessage, '[{');
        if ($jsonStart === false) {
            return null;
        }

        $json = substr($errorMessage, $jsonStart);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return null;
        }

        $matchRecords = $decoded[0]['duplicateResult']['matchResults'][0]['matchRecords'] ?? [];
        foreach ($matchRecords as $matchRecord) {
            $id = $matchRecord['record']['Id'] ?? null;
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    private function fetchSalesforceContactId(string $customerId, ?string $salesChannelId): ?string
    {
        $result = $this->salesforceApi->getByExternalId(
            'Contact', 'Shopware_Customer_ID__c', $customerId, ['Id'], $salesChannelId
        );

        return $result['Id'] ?? null;
    }

    private function persistSyncResult(
        CustomerEntity $customer,
        Context        $syncContext,
        string         $status,
        ?string        $error,
        ?string        $sfContactId = null,
    ): void {
        $fields = [
            'salesforce_last_sync_status' => $status,
            'salesforce_last_sync_error'  => $error,
            'salesforce_last_sync_at'     => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];

        if ($status === 'success') {
            $fields['salesforce_contact_id']  = $sfContactId;
            $fields['salesforce_retry_count'] = 0;
        }

        $this->customerRepository->update([[
            'id'           => $customer->getId(),
            'customFields' => array_merge($customer->getCustomFields() ?? [], $fields),
        ]], $syncContext);
    }

    private function writeSyncLog(
        string  $customerId,
        string  $status,
        ?string $sfContactId,
        ?string $errorMessage,
        int     $durationMs,
        Context $context,
    ): void {
        $this->syncLogRepository->create([[
            'id'                  => Uuid::randomHex(),
            'customerId'          => $customerId,
            'status'              => $status,
            'salesforceContactId' => $sfContactId,
            'errorMessage'        => $errorMessage,
            'syncedAt'            => new \DateTimeImmutable(),
            'durationMs'          => $durationMs,
        ]], $context);
    }

    private function humanizeError(string $message): string
    {
        if (str_contains($message, 'Shopware_Customer_ID__c') && str_contains($message, '404')) {
            return 'Salesforce field "Shopware_Customer_ID__c" does not exist on Contact. '
                . 'Create it in Salesforce: Setup → Object Manager → Contact → Fields & Relationships → New '
                . '(Text, length 36, External ID ✓, Unique ✓). Then run: bin/console itde:salesforce:test';
        }

        if (str_contains($message, 'FIELD_INTEGRITY_EXCEPTION') && str_contains($message, 'MailingState')) {
            return 'Salesforce rejected the state/province value. '
                . 'The country may not have states enabled in Salesforce. '
                . 'Go to Setup → State and Country/Territory Picklists and verify the country is active: '
                . $message;
        }

        if (str_contains($message, 'INVALID_OR_NULL_FOR_RESTRICTED_PICKLIST') && str_contains($message, 'MailingCountryCode')) {
            return 'Salesforce country picklist does not include this country ISO code. '
                . 'Enable the country in Salesforce: Setup → State and Country/Territory Picklists. '
                . 'Contact was synced without the country/state fields as a fallback: '
                . $message;
        }

        if (str_contains($message, 'DUPLICATES_DETECTED')) {
            return 'Salesforce duplicate detection blocked the upsert and no existing Contact ID could be extracted automatically. '
                . 'Review the Salesforce duplicate rule or merge duplicate Contacts manually: '
                . $message;
        }

        return $message;
    }
}

