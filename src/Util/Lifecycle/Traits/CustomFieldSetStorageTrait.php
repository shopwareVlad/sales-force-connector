<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Util\Lifecycle\Traits;

trait CustomFieldSetStorageTrait
{
    private array $customFieldSets = [
        'salesforce_customer' => [
            'name'      => 'salesforce_customer',
            'config'    => ['label' => ['de-DE' => 'Salesforce Customer', 'en-GB' => 'Salesforce Customer']],
            'relations' => [['entityName' => 'customer']],
        ],
        'salesforce_order' => [
            'name'      => 'salesforce_order',
            'config'    => ['label' => ['de-DE' => 'Salesforce Order', 'en-GB' => 'Salesforce Order']],
            'relations' => [['entityName' => 'order']],
        ],
    ];
}