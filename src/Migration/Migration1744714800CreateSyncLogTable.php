<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1744714800CreateSyncLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1744714800;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `itde_salesforce_sync_log` (
                `id`                      BINARY(16)   NOT NULL,
                `customer_id`             BINARY(16)   NOT NULL,
                `status`                  VARCHAR(20)  NOT NULL,
                `salesforce_contact_id`   VARCHAR(100) NULL,
                `error_message`           LONGTEXT     NULL,
                `synced_at`               DATETIME(3)  NOT NULL,
                `duration_ms`             INT(11)      NULL,
                `created_at`              DATETIME(3)  NOT NULL,
                `updated_at`              DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.itde_salesforce_sync_log.customer_id` (`customer_id`),
                INDEX `idx.itde_salesforce_sync_log.synced_at`   (`synced_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

