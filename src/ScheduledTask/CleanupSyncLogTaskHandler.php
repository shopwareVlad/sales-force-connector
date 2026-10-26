<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanupSyncLogTask::class)]
class CleanupSyncLogTaskHandler extends ScheduledTaskHandler
{
    private const RETENTION_DAYS = 90;

    public function __construct(
        \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $scheduledTaskRepository,
        private readonly Connection      $connection,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        yield CleanupSyncLogTask::class;
    }

    public function run(): void
    {
        $cutoff = (new \DateTimeImmutable(sprintf('-%d days', self::RETENTION_DAYS)))->format('Y-m-d H:i:s');

        $deleted = $this->connection->executeStatement(
            'DELETE FROM `itde_salesforce_sync_log` WHERE `synced_at` < :cutoff',
            ['cutoff' => $cutoff]
        );

        $this->logger->info('Salesforce sync log cleanup completed', [
            'deleted'        => $deleted,
            'retentionDays'  => self::RETENTION_DAYS,
            'cutoff'         => $cutoff,
        ]);
    }
}
