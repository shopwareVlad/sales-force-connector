<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Entity\SyncLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SyncLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SyncLogEntity::class;
    }
}

