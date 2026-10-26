<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Util\Lifecycle\Traits;

use Shopware\Core\System\CustomField\CustomFieldTypes;

trait CustomFieldStorageTrait
{
    private array $customFields = [
        'salesforce_customer' => [
            [
                'name' => 'salesforce_contact_id',
                'type' => CustomFieldTypes::TEXT,
                'config' => ['customFieldPosition' => 1, 'label' => ['de-DE' => 'Salesforce Contact Id', 'en-GB' => 'Salesforce Contact Id']]
            ],
            [
                'name' => 'salesforce_last_sync_at',
                'type' => CustomFieldTypes::DATETIME,
                'config' => ['customFieldPosition' => 2, 'label' => ['de-DE' => 'Salesforce Last Synchronization at', 'en-GB' => 'Salesforce Last Synchronization at']]
            ],
            [
                'name' => 'salesforce_last_sync_status',
                'type' => CustomFieldTypes::TEXT,
                'config' => ['customFieldPosition' => 3, 'label' => ['de-DE' => 'Salesforce Last Synchronization Status', 'en-GB' => 'Salesforce Last Synchronization Status']]
            ],
            [
                'name' => 'salesforce_last_sync_error',
                'type' => CustomFieldTypes::TEXT,
                'config' => ['customFieldPosition' => 4, 'label' => ['de-DE' => 'Salesforce Last Synchronization Error', 'en-GB' => 'Salesforce Last Synchronization Error'], 'componentName' => 'sw-textarea-field']
            ],
            [
                'name' => 'salesforce_retry_count',
                'type' => CustomFieldTypes::INT,
                'config' => ['customFieldPosition' => 5, 'label' => ['de-DE' => 'Salesforce Retry Count', 'en-GB' => 'Salesforce Retry Count']]
            ],
        ],

        'salesforce_order' => [
            [
                'name' => 'salesforce_opportunity_id',
                'type' => CustomFieldTypes::TEXT,
                'config' => ['customFieldPosition' => 1, 'label' => ['de-DE' => 'Salesforce Opportunity Id', 'en-GB' => 'Salesforce Opportunity Id']]
            ],
            [
                'name' => 'salesforce_order_sync_at',
                'type' => CustomFieldTypes::DATETIME,
                'config' => ['customFieldPosition' => 2, 'label' => ['de-DE' => 'Salesforce Order Sync at', 'en-GB' => 'Salesforce Order Sync at']]
            ],
            [
                'name' => 'salesforce_order_sync_status',
                'type' => CustomFieldTypes::TEXT,
                'config' => ['customFieldPosition' => 3, 'label' => ['de-DE' => 'Salesforce Order Sync Status', 'en-GB' => 'Salesforce Order Sync Status']]
            ],
            [
                'name' => 'salesforce_order_sync_error',
                'type' => CustomFieldTypes::TEXT,
                'config' => ['customFieldPosition' => 4, 'label' => ['de-DE' => 'Salesforce Order Sync Error', 'en-GB' => 'Salesforce Order Sync Error'], 'componentName' => 'sw-textarea-field']
            ],
            [
                'name' => 'salesforce_order_retry_count',
                'type' => CustomFieldTypes::INT,
                'config' => ['customFieldPosition' => 5, 'label' => ['de-DE' => 'Salesforce Order Retry Count', 'en-GB' => 'Salesforce Order Retry Count']]
            ],
        ],
    ];
}