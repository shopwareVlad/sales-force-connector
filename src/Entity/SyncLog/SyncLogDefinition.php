<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Entity\SyncLog;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SyncLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'itde_salesforce_sync_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SyncLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SyncLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            new StringField('salesforce_contact_id', 'salesforceContactId'),
            new LongTextField('error_message', 'errorMessage'),
            (new DateTimeField('synced_at', 'syncedAt'))->addFlags(new Required()),
            new IntField('duration_ms', 'durationMs'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}

