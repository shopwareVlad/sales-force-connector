<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncCustomerMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: SyncAllCustomersTask::class)]
final class SyncAllCustomersTaskHandler extends ScheduledTaskHandler
{
    private const BATCH_SIZE = 250;

    public function __construct(
        EntityRepository    $scheduledTaskRepository,
        LoggerInterface     $logger,
        private readonly EntityRepository    $customerRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context  = Context::createDefaultContext();
        $offset   = 0;
        $total    = 0;

        do {
            $criteria = (new Criteria())
                ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
                ->setOffset($offset)
                ->setLimit(self::BATCH_SIZE);

            $customers = $this->customerRepository->search($criteria, $context);

            foreach ($customers as $customer) {
                $this->bus->dispatch(new SyncCustomerMessage($customer->getId(), $context));
                ++$total;
            }

            $offset += $customers->count();
        } while ($customers->count() === self::BATCH_SIZE);
    }
}
