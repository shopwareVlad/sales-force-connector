<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupSyncLogTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'itde_salesforce_connector.cleanup_sync_log';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
