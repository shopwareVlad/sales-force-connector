<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class RetryFailedOrderSyncsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'itde_salesforce_connector.retry_failed_order_syncs';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
