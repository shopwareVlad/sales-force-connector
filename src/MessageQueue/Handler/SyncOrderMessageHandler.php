<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\MessageQueue\Handler;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncOrderMessage;
use ItDe\SalesforceConnector\Service\OpportunityLineItemSyncer;
use ItDe\SalesforceConnector\Service\OrderOpportunityMapper;
use ItDe\SalesforceConnector\Service\SalesforceApi;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final readonly class SyncOrderMessageHandler
{
    public function __construct(
        private EntityRepository         $orderRepository,
        private SalesforceApi            $salesforceApi,
        private OrderOpportunityMapper   $opportunityMapper,
        private OpportunityLineItemSyncer $lineItemSyncer,
        private LoggerInterface          $logger,
    ) {
    }

    public function __invoke(SyncOrderMessage $message): void
    {
        $startMs = (int) round(microtime(true) * 1000);
        $context = $message->context;
        $order   = $this->loadOrder($message->orderId, $context);

        if ($order === null) {
            return;
        }

        $syncContext = clone $context;
        $syncContext->addState('salesforce-sync-update');

        try {
            $sfOpportunityId = $this->syncOpportunity($order);
            $durationMs      = (int) round(microtime(true) * 1000) - $startMs;

            $this->persistSyncResult($order, $syncContext, 'success', null, $sfOpportunityId);

            $this->logger->info('Salesforce order sync succeeded', [
                'orderId'                 => $order->getId(),
                'orderNumber'             => $order->getOrderNumber(),
                'salesforceOpportunityId' => $sfOpportunityId,
                'durationMs'              => $durationMs,
            ]);
        } catch (\Throwable $e) {
            $durationMs = (int) round(microtime(true) * 1000) - $startMs;
            $error      = $e->getMessage();

            $this->logger->error('Salesforce order sync failed', [
                'orderId'    => $order->getId(),
                'orderNumber'=> $order->getOrderNumber(),
                'error'      => $error,
                'durationMs' => $durationMs,
            ]);

            $this->persistSyncResult($order, $syncContext, 'error', $error);
        }
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('orderCustomer')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState')
            ->addAssociation('lineItems')
            ->addAssociation('lineItems.product');

        return $this->orderRepository->search($criteria, $context)->first();
    }

    private function syncOpportunity(OrderEntity $order): ?string
    {
        $salesChannelId = $order->getSalesChannelId();
        $payload = $this->opportunityMapper->toPayload($order);

        $this->salesforceApi->upsertByExternalId(
            'Opportunity',
            'Shopware_Order_ID__c',
            $order->getId(),
            $payload,
            $salesChannelId
        );

        $sfOpportunityId = $this->fetchOpportunityId($order->getId(), $salesChannelId);

        if ($sfOpportunityId !== null) {
            $contactId = $this->resolveContactId($order, $salesChannelId);
            if ($contactId !== null) {
                try {
                    $this->salesforceApi->updateById(
                        'Opportunity',
                        $sfOpportunityId,
                        ['ContactId' => $contactId],
                        $salesChannelId
                    );
                } catch (\Throwable $e) {
                    $this->logger->warning('Salesforce order sync: could not set ContactId on Opportunity', [
                        'orderId'         => $order->getId(),
                        'sfOpportunityId' => $sfOpportunityId,
                        'contactId'       => $contactId,
                        'error'           => $e->getMessage(),
                    ]);
                }
            }

            try {
                $this->lineItemSyncer->sync($sfOpportunityId, $order, $salesChannelId);
            } catch (\Throwable $e) {
                $this->logger->warning('Salesforce order sync: line item sync failed (Opportunity was still saved)', [
                    'orderId' => $order->getId(),
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $sfOpportunityId;
    }

    private function resolveContactId(OrderEntity $order, ?string $salesChannelId): ?string
    {
        $customer = $order->getOrderCustomer();
        if ($customer === null) {
            return null;
        }

        $customerId = $customer->getCustomerId();
        if ($customerId !== null) {
            try {
                $result = $this->salesforceApi->getByExternalId(
                    'Contact',
                    'Shopware_Customer_ID__c',
                    $customerId,
                    ['Id'],
                    $salesChannelId
                );
                if (!empty($result['Id'])) {
                    return $result['Id'];
                }
            } catch (\Throwable) {
            }
        }

        $email = $customer->getEmail();
        if ($email === null || $email === '') {
            return null;
        }

        try {
            $records = $this->salesforceApi->queryAll(
                sprintf("SELECT Id FROM Contact WHERE Email = '%s' LIMIT 1", addslashes($email)),
                $salesChannelId
            );
            if (!empty($records[0]['Id'])) {
                return $records[0]['Id'];
            }
        } catch (\Throwable) {
        }

        $firstName = $customer->getFirstName() ?: 'Guest';
        $lastName  = $customer->getLastName()  ?: 'Customer';

        try {
            $result = $this->salesforceApi->createRecord('Contact', array_filter([
                'FirstName' => $firstName,
                'LastName'  => $lastName,
                'Email'     => $email,
            ]), $salesChannelId);

            $sfId = $result['id'] ?? $result['Id'] ?? null;

            if ($sfId) {
                $this->logger->info('Salesforce order sync: created guest Contact', [
                    'orderId'    => $order->getId(),
                    'email'      => $email,
                    'sfContactId' => $sfId,
                ]);
            }

            return $sfId;
        } catch (\Throwable $e) {
            $this->logger->warning('Salesforce order sync: could not create guest Contact', [
                'orderId' => $order->getId(),
                'email'   => $email,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function fetchOpportunityId(string $orderId, ?string $salesChannelId): ?string
    {
        $result = $this->salesforceApi->getByExternalId(
            'Opportunity',
            'Shopware_Order_ID__c',
            $orderId,
            ['Id'],
            $salesChannelId
        );

        return $result['Id'] ?? null;
    }

    private function persistSyncResult(
        OrderEntity $order,
        Context     $context,
        string      $status,
        ?string     $error,
        ?string     $sfOpportunityId = null,
    ): void {
        $fields = [
            'salesforce_order_sync_status' => $status,
            'salesforce_order_sync_error'  => $error,
            'salesforce_order_sync_at'     => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];

        if ($status === 'success') {
            $fields['salesforce_opportunity_id']    = $sfOpportunityId;
            $fields['salesforce_order_retry_count'] = 0;
        }

        $this->orderRepository->update([[
            'id'           => $order->getId(),
            'customFields' => array_merge($order->getCustomFields() ?? [], $fields),
        ]], $context);
    }
}

