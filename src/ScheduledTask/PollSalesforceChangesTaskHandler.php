<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use ItDe\SalesforceConnector\MessageQueue\Message\InboundSyncContactMessage;
use ItDe\SalesforceConnector\Service\ConfigProvider;
use ItDe\SalesforceConnector\Service\SalesforceApi;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: PollSalesforceChangesTask::class)]
class PollSalesforceChangesTaskHandler extends ScheduledTaskHandler
{
    private const DEFAULT_LOOKBACK_MINUTES = 15;

    public function __construct(
        EntityRepository              $scheduledTaskRepository,
        private readonly SalesforceApi       $salesforceApi,
        private readonly ConfigProvider      $configProvider,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface     $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        yield PollSalesforceChangesTask::class;
    }

    public function run(): void
    {
        if (!$this->configProvider->isInboundPollEnabled()) {
            return;
        }

        $since   = $this->getSinceDateTime();
        $pollAt  = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $context = Context::createDefaultContext();

        try {
            $contacts = $this->queryModifiedContacts($since);
        } catch (\Throwable $e) {
            $this->logger->error('Salesforce inbound poll failed', ['error' => $e->getMessage()]);
            return;
        }

        $dispatched = 0;
        foreach ($contacts as $contact) {
            $sfId = $contact['Id'] ?? null;
            if (!$sfId) {
                continue;
            }

            $this->bus->dispatch(new InboundSyncContactMessage($sfId, $context));
            ++$dispatched;
        }

        $this->configProvider->setLastPollAt($pollAt);

        $this->logger->info('Salesforce inbound poll complete', [
            'since'      => $since,
            'dispatched' => $dispatched,
        ]);
    }

    private function getSinceDateTime(): string
    {
        $lastPollAt = $this->configProvider->getLastPollAt();

        if ($lastPollAt instanceof \DateTimeImmutable) {
            return $lastPollAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }

        return (new \DateTimeImmutable(
            sprintf('-%d minutes', self::DEFAULT_LOOKBACK_MINUTES),
            new \DateTimeZone('UTC')
        ))->format('Y-m-d\TH:i:s\Z');
    }

    private function queryModifiedContacts(string $since): array
    {
        $soql = sprintf(
            "SELECT Id FROM Contact WHERE Shopware_Customer_ID__c != null AND LastModifiedDate > %s",
            $since
        );

        return $this->salesforceApi->queryAll($soql);
    }
}

