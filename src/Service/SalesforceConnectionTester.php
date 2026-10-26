<?php

declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

class SalesforceConnectionTester
{
    private const REQUIRED_CONTACT_FIELDS = [
        'Shopware_Customer_ID__c' =>
            'Create in Salesforce: Setup → Object Manager → Contact → Fields & Relationships → New'
            . ' (Text, length 36, External ID ✓, Unique ✓).',
    ];

    public function __construct(private readonly SalesforceApi $api)
    {
    }

    public function run(?string $salesChannelId = null): array
    {
        $steps = [];

        $fieldNames = [];
        try {
            $versions = $this->api->listVersions($salesChannelId);
            $steps[]  = $this->pass(
                'JWT authentication & API connectivity',
                sprintf('Connected. %d API version(s) returned.', \count($versions))
            );
        } catch (\Throwable $e) {
            $steps[] = $this->fail(
                'JWT authentication & API connectivity',
                $e->getMessage() . ' — Check loginUrl, clientId, username and jwtPrivateKeyPem in plugin settings.'
            );

            return ['success' => false, 'steps' => $steps];
        }

        try {
            $fieldNames = $this->api->getSObjectFieldNames('Contact', $salesChannelId);
            $steps[]    = $this->pass(
                'Contact object accessible',
                sprintf('%d field(s) found on the Contact object.', \count($fieldNames))
            );
        } catch (\Throwable $e) {
            $steps[] = $this->fail(
                'Contact object accessible',
                $e->getMessage() . ' — Ensure the API user has Read access on Contact.'
            );
        }

        $allPassed = true;
        foreach (self::REQUIRED_CONTACT_FIELDS as $fieldName => $howToFix) {
            $exists = \in_array($fieldName, $fieldNames, true);
            if (!$exists) {
                $allPassed = false;
            }

            $steps[] = $exists
                ? $this->pass(sprintf('Contact.%s', $fieldName), 'Field exists.')
                : $this->fail(sprintf('Contact.%s', $fieldName), 'MISSING! ' . $howToFix);
        }

        return ['success' => $allPassed, 'steps' => $steps];
    }

    private function pass(string $name, string $message): array
    {
        return ['name' => $name, 'passed' => true, 'message' => $message];
    }

    private function fail(string $name, string $message): array
    {
        return ['name' => $name, 'passed' => false, 'message' => $message];
    }
}
