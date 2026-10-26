<?php

declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Shopware\Core\Checkout\Customer\CustomerEntity;

class CustomerContactMapper
{
    private const DEFAULT_MAPPING = [
        'email'      => 'Email',
        'firstName'  => 'FirstName',
        'lastName'   => 'LastName',
        'phone'      => 'Phone',
        'street'     => 'MailingStreet',
        'zipcode'    => 'MailingPostalCode',
        'city'       => 'MailingCity',
        'countryIso' => 'MailingCountryCode',
        'stateName'  => 'MailingState',
    ];

    public function __construct(
        private readonly ConfigProvider $configProvider,
    ) {
    }

    public function toPayload(CustomerEntity $customer, ?string $salesChannelId = null): array
    {
        $mapping = $this->resolveMapping($salesChannelId);
        $addr    = $customer->getDefaultBillingAddress();

        $source = [
            'email'      => $customer->getEmail(),
            'firstName'  => $customer->getFirstName(),
            'lastName'   => $customer->getLastName(),
            'phone'      => $addr?->getPhoneNumber(),
            'street'     => $addr?->getStreet(),
            'zipcode'    => $addr?->getZipcode(),
            'city'       => $addr?->getCity(),
            'countryIso' => $addr?->getCountry()?->getIso(),
            'stateName'  => $this->resolveStateCode($addr?->getCountryState()?->getShortCode()),
        ];

        $payload = [];
        foreach ($mapping as $shopwareKey => $sfField) {
            $value = $source[$shopwareKey] ?? null;
            if ($value !== null && $value !== '') {
                $payload[$sfField] = $value;
            }
        }

        return $payload;
    }

    private function resolveMapping(?string $salesChannelId): array
    {
        $configured = $this->configProvider->getFieldMapping($salesChannelId);

        return empty($configured) ? self::DEFAULT_MAPPING : $configured;
    }

    private function resolveStateCode(?string $shortCode): ?string
    {
        if ($shortCode === null || trim($shortCode) === '') {
            return null;
        }

        $pos = strpos($shortCode, '-');
        if ($pos !== false) {
            return substr($shortCode, $pos + 1);
        }

        return $shortCode;
    }
}
