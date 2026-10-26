<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncCustomerMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: RetryFailedSyncsTask::class)]
class RetryFailedSyncsTaskHandler extends ScheduledTaskHandler
{
    private const MAX_RETRIES = 5;

    public function __construct(
        EntityRepository     $scheduledTaskRepository,
        private readonly EntityRepository  $customerRepository,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface   $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        yield RetryFailedSyncsTask::class;
    }

    public function run(): void
    {
        $context  = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.salesforce_last_sync_status', 'error'));
        $criteria->addFilter(new RangeFilter('customFields.salesforce_retry_count', [
            RangeFilter::LT => self::MAX_RETRIES,
        ]));
        $criteria->setLimit(500);

        $customers  = $this->customerRepository->search($criteria, $context);
        $dispatched = 0;

        foreach ($customers as $customer) {
            $retryCount = (int) ($customer->getCustomFields()['salesforce_retry_count'] ?? 0);

            $this->customerRepository->update([[
                'id'           => $customer->getId(),
                'customFields' => array_merge($customer->getCustomFields() ?? [], [
                    'salesforce_retry_count' => $retryCount + 1,
                ]),
            ]], $context);

            $this->bus->dispatch(new SyncCustomerMessage($customer->getId(), $context));
            ++$dispatched;
        }

        $this->logger->info('Salesforce retry task dispatched sync messages', [
            'dispatched' => $dispatched,
            'total'      => $customers->getTotal(),
        ]);
    }
}
