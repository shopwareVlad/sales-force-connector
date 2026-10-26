<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

class SalesforceApi
{
    public function __construct(
        private readonly SalesforceHttpClient $client,
        private readonly ConfigProvider $config,
    ) {
    }

    public function listVersions(?string $salesChannelId = null): array
    {
        return $this->client->get('/services/data/', $salesChannelId);
    }

    public function upsertByExternalId(
        string $sObject,
        string $externalIdField,
        string $externalIdValue,
        array $payload,
        ?string $salesChannelId = null
    ): array {
        $api = $this->config->getApiVersion($salesChannelId);

        $path = sprintf(
            '/services/data/%s/sobjects/%s/%s/%s',
            $api,
            rawurlencode($sObject),
            rawurlencode($externalIdField),
            rawurlencode($externalIdValue)
        );

        return $this->client->patch($path, $payload, $salesChannelId);
    }

    public function describeSObject(string $sObject, ?string $salesChannelId = null): array
    {
        $api  = $this->config->getApiVersion($salesChannelId);
        $path = sprintf('/services/data/%s/sobjects/%s/describe/', $api, rawurlencode($sObject));

        return $this->client->get($path, $salesChannelId);
    }

    public function getSObjectFieldNames(string $sObject, ?string $salesChannelId = null): array
    {
        $describe = $this->describeSObject($sObject, $salesChannelId);
        $fields   = $describe['fields'] ?? [];

        return array_column($fields, 'name');
    }

    public function getContactById(string $sfId, ?string $salesChannelId = null): array
    {
        $api    = $this->config->getApiVersion($salesChannelId);
        $fields = rawurlencode('Id,FirstName,LastName,Email,Phone,MailingStreet,MailingPostalCode,MailingCity,MailingCountryCode,MailingState,Shopware_Customer_ID__c');
        $path   = sprintf('/services/data/%s/sobjects/Contact/%s?fields=%s', $api, rawurlencode($sfId), $fields);

        return $this->client->get($path, $salesChannelId);
    }

    public function getByExternalId(
        string $sObject,
        string $externalIdField,
        string $externalIdValue,
        array $fields = ['Id'],
        ?string $salesChannelId = null
    ): array {
        $api = $this->config->getApiVersion($salesChannelId);

        $path = sprintf(
            '/services/data/%s/sobjects/%s/%s/%s?fields=%s',
            $api,
            rawurlencode($sObject),
            rawurlencode($externalIdField),
            rawurlencode($externalIdValue),
            rawurlencode(implode(',', $fields))
        );

        return $this->client->get($path, $salesChannelId);
    }

    public function updateById(
        string $sObject,
        string $sfId,
        array $payload,
        ?string $salesChannelId = null
    ): void {
        $api  = $this->config->getApiVersion($salesChannelId);
        $path = sprintf('/services/data/%s/sobjects/%s/%s', $api, rawurlencode($sObject), rawurlencode($sfId));
        $this->client->patch($path, $payload, $salesChannelId);
    }

    public function createRecord(string $sObject, array $payload, ?string $salesChannelId = null): array
    {
        $api  = $this->config->getApiVersion($salesChannelId);
        $path = sprintf('/services/data/%s/sobjects/%s', $api, rawurlencode($sObject));
        return $this->client->post($path, $payload, $salesChannelId);
    }

    public function deleteRecord(string $sObject, string $sfId, ?string $salesChannelId = null): void
    {
        $api  = $this->config->getApiVersion($salesChannelId);
        $path = sprintf('/services/data/%s/sobjects/%s/%s', $api, rawurlencode($sObject), rawurlencode($sfId));
        $this->client->delete($path, $salesChannelId);
    }

    public function queryAll(string $soql, ?string $salesChannelId = null): array
    {
        $api  = $this->config->getApiVersion($salesChannelId);
        $path = sprintf('/services/data/%s/query?q=%s', $api, rawurlencode($soql));

        $all = [];
        do {
            $result = $this->client->get($path, $salesChannelId);
            $all    = array_merge($all, $result['records'] ?? []);
            $done   = $result['done'] ?? true;
            $path   = $result['nextRecordsUrl'] ?? null;
        } while (!$done && $path !== null);

        return $all;
    }
}
