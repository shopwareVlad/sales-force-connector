<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\MessageQueue\Handler;

use ItDe\SalesforceConnector\MessageQueue\Message\InboundSyncContactMessage;
use ItDe\SalesforceConnector\Service\SalesforceApi;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final readonly class InboundSyncContactMessageHandler
{
    public function __construct(
        private EntityRepository $customerRepository,
        private SalesforceApi    $salesforceApi,
        private LoggerInterface  $logger,
    ) {
    }

    public function __invoke(InboundSyncContactMessage $message): void
    {
        $sfId    = $message->salesforceContactId;
        $context = $message->context;
        $context->addState('salesforce-sync-update');

        try {
            $contact = $this->salesforceApi->getContactById($sfId);
        } catch (\Throwable $e) {
            $this->logger->error('Inbound SF sync: failed to fetch Contact', [
                'salesforceContactId' => $sfId,
                'error'               => $e->getMessage(),
            ]);
            return;
        }

        $shopwareCustomerId = $contact['Shopware_Customer_ID__c'] ?? null;
        if (!$shopwareCustomerId) {
            $this->logger->warning('Inbound SF sync: Contact has no Shopware_Customer_ID__c', [
                'salesforceContactId' => $sfId,
            ]);
            return;
        }

        $criteria = new Criteria([$shopwareCustomerId]);
        $criteria->addAssociation('defaultBillingAddress');
        $customer = $this->customerRepository->search($criteria, $context)->first();

        if ($customer === null) {
            $this->logger->warning('Inbound SF sync: Shopware customer not found', [
                'customerId'          => $shopwareCustomerId,
                'salesforceContactId' => $sfId,
            ]);
            return;
        }

        $customerUpdate = array_filter([
            'id'        => $customer->getId(),
            'firstName' => $contact['FirstName'] ?? null,
            'lastName'  => $contact['LastName']  ?? null,
            'email'     => $contact['Email']     ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $addr = $customer->getDefaultBillingAddress();
        if ($addr !== null) {
            $addrUpdate = array_filter([
                'id'          => $addr->getId(),
                'phoneNumber' => $contact['Phone']             ?? null,
                'street'      => $contact['MailingStreet']     ?? null,
                'zipcode'     => $contact['MailingPostalCode'] ?? null,
                'city'        => $contact['MailingCity']       ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            if (count($addrUpdate) > 1) {
                $customerUpdate['defaultBillingAddress'] = $addrUpdate;
            }
        }

        if (count($customerUpdate) > 1) {
            $this->customerRepository->update([$customerUpdate], $context);

            $this->logger->info('Inbound SF sync: Shopware customer updated from Salesforce', [
                'customerId'          => $customer->getId(),
                'salesforceContactId' => $sfId,
            ]);
        }
    }
}
