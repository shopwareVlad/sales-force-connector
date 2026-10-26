<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SyncAllOrdersTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'itde_salesforce_connector.sync_all_orders';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
