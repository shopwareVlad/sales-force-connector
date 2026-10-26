<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncOrderMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: RetryFailedOrderSyncsTask::class)]
final class RetryFailedOrderSyncsTaskHandler extends ScheduledTaskHandler
{
    private const MAX_RETRIES = 5;

    public function __construct(
        EntityRepository             $scheduledTaskRepository,
        private readonly LoggerInterface     $syncLogger,
        private readonly EntityRepository    $orderRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $context  = Context::createDefaultContext();
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customFields.salesforce_order_sync_status', 'error'))
            ->addFilter(new RangeFilter('customFields.salesforce_order_retry_count', [
                RangeFilter::LT => self::MAX_RETRIES,
            ]))
            ->setLimit(500);

        $orders     = $this->orderRepository->search($criteria, $context);
        $dispatched = 0;

        foreach ($orders as $order) {
            $retryCount = (int) ($order->getCustomFields()['salesforce_order_retry_count'] ?? 0);

            $this->orderRepository->update([[
                'id'           => $order->getId(),
                'customFields' => array_merge($order->getCustomFields() ?? [], [
                    'salesforce_order_retry_count' => $retryCount + 1,
                ]),
            ]], $context);

            $this->bus->dispatch(new SyncOrderMessage($order->getId(), $context));
            ++$dispatched;
        }

        $this->syncLogger->info('Salesforce order retry task dispatched sync messages', [
            'dispatched' => $dispatched,
            'total'      => $orders->getTotal(),
        ]);
    }
}
