<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Controller\Api;

use ItDe\SalesforceConnector\MessageQueue\Message\InboundSyncContactMessage;
use ItDe\SalesforceConnector\MessageQueue\Message\SyncCustomerMessage;
use ItDe\SalesforceConnector\MessageQueue\Message\SyncOrderMessage;
use ItDe\SalesforceConnector\Service\ConfigProvider;
use ItDe\SalesforceConnector\Service\SalesforceTokenProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class SyncLogController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository       $syncLogRepository,
        private readonly EntityRepository       $customerRepository,
        private readonly EntityRepository       $orderRepository,
        private readonly MessageBusInterface    $bus,
        private readonly ConfigProvider         $configProvider,
        private readonly SalesforceTokenProvider $tokenProvider,
    ) {
    }

    #[Route(
        path: '/api/_action/itde-salesforce/sync-stats',
        name: 'api.action.itde_salesforce.sync_stats',
        methods: ['GET'],
        defaults: ['_acl' => ['customer.viewer']]
    )]
    public function getSyncStats(Request $request): JsonResponse
    {
        $context = Context::createDefaultContext();
        $today   = (new \DateTimeImmutable())->setTime(0, 0, 0)->format(\DateTimeInterface::ATOM);

        $page        = max(1, (int) $request->query->get('page', 1));
        $limit       = max(1, min(100, (int) $request->query->get('limit', 25)));
        $statusFilter = $request->query->get('status', '');
        $offset      = ($page - 1) * $limit;

        $successToday = $this->syncLogRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('status', 'success'))
                ->addFilter(new RangeFilter('syncedAt', [RangeFilter::GTE => $today]))
                ->setLimit(0),
            $context
        )->getTotal();

        $errorToday = $this->syncLogRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('status', 'error'))
                ->addFilter(new RangeFilter('syncedAt', [RangeFilter::GTE => $today]))
                ->setLimit(0),
            $context
        )->getTotal();

        $totalAllTime = $this->syncLogRepository->search(
            (new Criteria())->setLimit(0),
            $context
        )->getTotal();

        $logCriteria = (new Criteria())
            ->addSorting(new FieldSorting('syncedAt', FieldSorting::DESCENDING))
            ->setOffset($offset)
            ->setLimit($limit);

        if ($statusFilter !== '') {
            $logCriteria->addFilter(new EqualsFilter('status', $statusFilter));
        }

        $recent = $this->syncLogRepository->search($logCriteria, $context);

        $recentRows = array_map(static fn ($e) => [
            'id'                  => $e->getId(),
            'customerId'          => $e->getCustomerId(),
            'status'              => $e->getStatus(),
            'salesforceContactId' => $e->getSalesforceContactId(),
            'errorMessage'        => $e->getErrorMessage(),
            'syncedAt'            => $e->getSyncedAt()?->format(\DateTimeInterface::ATOM),
            'durationMs'          => $e->getDurationMs(),
        ], array_values($recent->getElements()));

        $lastPollAt = $this->configProvider->getLastPollAt();

        return new JsonResponse([
            'customerSync' => [
                'successToday' => $successToday,
                'errorToday'   => $errorToday,
                'totalAllTime' => $totalAllTime,
            ],
            'lastPollAt'   => $lastPollAt?->format(\DateTimeInterface::ATOM),
            'recentLogs'   => $recentRows,
            'logTotal'     => $recent->getTotal(),
        ]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/order-sync-stats',
        name: 'api.action.itde_salesforce.order_sync_stats',
        methods: ['GET'],
        defaults: ['_acl' => ['order.viewer']]
    )]
    public function getOrderSyncStats(Request $request): JsonResponse
    {
        $context = Context::createDefaultContext();
        $today   = (new \DateTimeImmutable())->setTime(0, 0, 0)->format(\DateTimeInterface::ATOM);

        $page         = max(1, (int) $request->query->get('page', 1));
        $limit        = max(1, min(100, (int) $request->query->get('limit', 25)));
        $statusFilter = $request->query->get('status', '');
        $offset       = ($page - 1) * $limit;

        $successToday = $this->orderRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('customFields.salesforce_order_sync_status', 'success'))
                ->addFilter(new RangeFilter('customFields.salesforce_order_sync_at', [RangeFilter::GTE => $today]))
                ->setLimit(0),
            $context
        )->getTotal();

        $errorTotal = $this->orderRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('customFields.salesforce_order_sync_status', 'error'))
                ->setLimit(0),
            $context
        )->getTotal();

        $neverSynced = $this->orderRepository->search(
            (new Criteria())
                ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                    new NotFilter(NotFilter::CONNECTION_AND, [
                        new EqualsFilter('customFields.salesforce_order_sync_status', null),
                    ]),
                ]))
                ->setLimit(0),
            $context
        )->getTotal();

        $recentCriteria = (new Criteria())
            ->addAssociation('orderCustomer')
            ->addSorting(new FieldSorting('customFields.salesforce_order_sync_at', FieldSorting::DESCENDING))
            ->setOffset($offset)
            ->setLimit($limit);

        if ($statusFilter !== '') {
            $recentCriteria->addFilter(new EqualsFilter('customFields.salesforce_order_sync_status', $statusFilter));
        } else {
            $recentCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsFilter('customFields.salesforce_order_sync_status', null),
            ]));
        }

        $recent = $this->orderRepository->search($recentCriteria, $context);

        $sfBaseUrl = $this->getInstanceUrl();

        $rows = array_map(static function ($order) use ($sfBaseUrl) {
            $cf = $order->getCustomFields() ?? [];
            $sfOpportunityId = $cf['salesforce_opportunity_id'] ?? null;

            return [
                'id'               => $order->getId(),
                'orderNumber'      => $order->getOrderNumber(),
                'customerName'     => trim(
                    ($order->getOrderCustomer()?->getFirstName() ?? '') . ' ' .
                    ($order->getOrderCustomer()?->getLastName()  ?? '')
                ) ?: '—',
                'customerEmail'    => $order->getOrderCustomer()?->getEmail(),
                'status'           => $cf['salesforce_order_sync_status'] ?? null,
                'syncedAt'         => $cf['salesforce_order_sync_at']     ?? null,
                'errorMessage'     => $cf['salesforce_order_sync_error']  ?? null,
                'sfOpportunityId'  => $sfOpportunityId,
                'sfLink'           => ($sfBaseUrl && $sfOpportunityId)
                    ? "{$sfBaseUrl}/lightning/r/Opportunity/{$sfOpportunityId}/view"
                    : null,
            ];
        }, array_values($recent->getElements()));

        return new JsonResponse([
            'orderSync' => [
                'successToday' => $successToday,
                'errorTotal'   => $errorTotal,
                'neverSynced'  => $neverSynced,
            ],
            'recentLogs' => $rows,
            'logTotal'   => $recent->getTotal(),
        ]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/sync-log/{customerId}',
        name: 'api.action.itde_salesforce.sync_log',
        methods: ['GET'],
        defaults: ['_acl' => ['customer.viewer']]
    )]
    public function getSyncLog(string $customerId, Request $request): JsonResponse
    {
        $context = Context::createDefaultContext();
        $limit   = max(1, min(100, (int) $request->query->get('limit', 25)));

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerId', $customerId))
            ->addSorting(new FieldSorting('syncedAt', FieldSorting::DESCENDING))
            ->setLimit($limit);

        $entries = $this->syncLogRepository->search($criteria, $context);

        $rows = array_map(static function ($e) {
            return [
                'id'                  => $e->getId(),
                'status'              => $e->getStatus(),
                'salesforceContactId' => $e->getSalesforceContactId(),
                'errorMessage'        => $e->getErrorMessage(),
                'syncedAt'            => $e->getSyncedAt()->format(\DateTimeInterface::ATOM),
                'durationMs'          => $e->getDurationMs(),
            ];
        }, $entries->getElements());

        $sfBaseUrl = $this->getInstanceUrl();

        return new JsonResponse([
            'total'     => $entries->getTotal(),
            'entries'   => array_values($rows),
            'sfBaseUrl' => $sfBaseUrl,
        ]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/sync-now/{customerId}',
        name: 'api.action.itde_salesforce.sync_now',
        methods: ['POST'],
        defaults: ['_acl' => ['customer.editor']]
    )]
    public function syncNow(string $customerId): JsonResponse
    {
        $context  = Context::createDefaultContext();
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->first();

        if ($customer === null) {
            return new JsonResponse(['error' => 'Customer not found.'], 404);
        }

        $this->bus->dispatch(new SyncCustomerMessage($customerId, $context));

        return new JsonResponse(['queued' => true, 'customerId' => $customerId]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/pull-from-salesforce/{customerId}',
        name: 'api.action.itde_salesforce.pull_from_salesforce',
        methods: ['POST'],
        defaults: ['_acl' => ['customer.editor']]
    )]
    public function pullFromSalesforce(string $customerId): JsonResponse
    {
        $context  = Context::createDefaultContext();
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->first();

        if ($customer === null) {
            return new JsonResponse(['error' => 'Customer not found.'], 404);
        }

        $sfContactId = $customer->getCustomFields()['salesforce_contact_id'] ?? null;

        if (!$sfContactId) {
            return new JsonResponse(['error' => 'No Salesforce Contact ID set on this customer. Run an outbound sync first.'], 422);
        }

        $this->bus->dispatch(new InboundSyncContactMessage($sfContactId, $context));

        return new JsonResponse(['queued' => true, 'customerId' => $customerId, 'salesforceContactId' => $sfContactId]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/order-sync-status/{orderId}',
        name: 'api.action.itde_salesforce.order_sync_status',
        methods: ['GET'],
        defaults: ['_acl' => ['order.viewer']]
    )]
    public function getOrderSyncStatus(string $orderId): JsonResponse
    {
        $context = Context::createDefaultContext();
        $order   = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();

        if ($order === null) {
            return new JsonResponse(['error' => 'Order not found.'], 404);
        }

        $cf = $order->getCustomFields() ?? [];

        $sfBaseUrl = $this->getInstanceUrl();

        return new JsonResponse([
            'status'              => $cf['salesforce_order_sync_status'] ?? null,
            'syncedAt'            => $cf['salesforce_order_sync_at'] ?? null,
            'errorMessage'        => $cf['salesforce_order_sync_error'] ?? null,
            'sfOpportunityId'     => $cf['salesforce_opportunity_id'] ?? null,
            'sfBaseUrl'           => $sfBaseUrl,
        ]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/sync-order-now/{orderId}',
        name: 'api.action.itde_salesforce.sync_order_now',
        methods: ['POST'],
        defaults: ['_acl' => ['order.editor']]
    )]
    public function syncOrderNow(string $orderId): JsonResponse
    {
        $context = Context::createDefaultContext();
        $order   = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();

        if ($order === null) {
            return new JsonResponse(['error' => 'Order not found.'], 404);
        }

        $this->bus->dispatch(new SyncOrderMessage($orderId, $context));

        return new JsonResponse(['queued' => true, 'orderId' => $orderId]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/sync-all-customers',
        name: 'api.action.itde_salesforce.sync_all_customers',
        methods: ['POST'],
        defaults: ['_acl' => ['customer.editor']]
    )]
    public function syncAllCustomers(): JsonResponse
    {
        $context = Context::createDefaultContext();
        $offset  = 0;
        $total   = 0;

        do {
            $criteria = (new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria())
                ->setOffset($offset)
                ->setLimit(250);

            $customers = $this->customerRepository->search($criteria, $context);

            foreach ($customers as $customer) {
                $this->bus->dispatch(new \ItDe\SalesforceConnector\MessageQueue\Message\SyncCustomerMessage(
                    $customer->getId(),
                    $context
                ));
                ++$total;
            }

            $offset += $customers->count();
        } while ($customers->count() === 250);

        return new JsonResponse(['queued' => true, 'total' => $total]);
    }

    #[Route(
        path: '/api/_action/itde-salesforce/sync-all-orders',
        name: 'api.action.itde_salesforce.sync_all_orders',
        methods: ['POST'],
        defaults: ['_acl' => ['order.editor']]
    )]
    public function syncAllOrders(): JsonResponse
    {
        $context = Context::createDefaultContext();
        $offset  = 0;
        $total   = 0;

        do {
            $criteria = (new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria())
                ->setOffset($offset)
                ->setLimit(250);

            $orders = $this->orderRepository->search($criteria, $context);

            foreach ($orders as $order) {
                $this->bus->dispatch(new SyncOrderMessage($order->getId(), $context));
                ++$total;
            }

            $offset += $orders->count();
        } while ($orders->count() === 250);

        return new JsonResponse(['queued' => true, 'total' => $total]);
    }

    private function getInstanceUrl(): ?string
    {
        try {
            return rtrim($this->tokenProvider->getToken()['instanceUrl'], '/');
        } catch (\Throwable) {
            return null;
        }
    }
}
