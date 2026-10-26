<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncOrderMessage;
use ItDe\SalesforceConnector\Service\ConfigProvider;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: SyncAllOrdersTask::class)]
final class SyncAllOrdersTaskHandler extends ScheduledTaskHandler
{
    private const BATCH_SIZE = 250;

    public function __construct(
        EntityRepository             $scheduledTaskRepository,
        LoggerInterface              $logger,
        private readonly EntityRepository    $orderRepository,
        private readonly MessageBusInterface $bus,
        private readonly ConfigProvider      $configProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if (!$this->configProvider->isOrderSyncEnabled()) {
            return;
        }

        $context  = Context::createDefaultContext();
        $statuses = $this->configProvider->getOrderSyncStatuses();
        $offset   = 0;
        $total    = 0;

        do {
            $criteria = (new Criteria())
                ->addSorting(new FieldSorting('orderDateTime', FieldSorting::ASCENDING))
                ->setOffset($offset)
                ->setLimit(self::BATCH_SIZE);

            if (!empty($statuses)) {
                $criteria->addAssociation('stateMachineState');
                $criteria->addFilter(new EqualsAnyFilter('stateMachineState.technicalName', $statuses));
            }

            $orders = $this->orderRepository->search($criteria, $context);

            foreach ($orders as $order) {
                $this->bus->dispatch(new SyncOrderMessage($order->getId(), $context));
                ++$total;
            }

            $offset += $orders->count();
        } while ($orders->count() === self::BATCH_SIZE);

        $this->logger->info('Salesforce scheduled order sync dispatched', [
            'total'    => $total,
            'statuses' => empty($statuses) ? 'all' : implode(', ', $statuses),
        ]);
    }
}

