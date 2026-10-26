<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class PollSalesforceChangesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'itde_salesforce_connector.poll_salesforce_changes';
    }

    public static function getDefaultInterval(): int
    {
        return 900;
    }
}
