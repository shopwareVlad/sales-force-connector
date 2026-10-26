<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1744714801AddTimestampsToSyncLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1744714801;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
