<?php

declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigProvider
{
    public const CONFIG_DOMAIN = 'ItDeSalesforceConnector.config.';

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getLoginUrl(?string $salesChannelId = null): string
    {
        $url = rtrim($this->getRequiredString('loginUrl', $salesChannelId), '/');

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    public function getClientId(?string $salesChannelId = null): string
    {
        return $this->getRequiredString('clientId', $salesChannelId);
    }

    public function getUsername(?string $salesChannelId = null): string
    {
        return $this->getRequiredString('username', $salesChannelId);
    }

    public function getJwtPrivateKeyPem(?string $salesChannelId = null): string
    {
        $pem = $this->getRequiredString('jwtPrivateKeyPem', $salesChannelId);

        if (!str_contains($pem, 'BEGIN')) {
            throw new \RuntimeException('JWT private key PEM looks invalid (missing BEGIN header).');
        }

        return $pem;
    }

    public function getConflictResolution(?string $salesChannelId = null): string
    {
        $value = $this->systemConfigService->get(self::CONFIG_DOMAIN . 'conflictResolution', $salesChannelId);

        return \in_array($value, ['shopware_wins', 'salesforce_wins'], true) ? $value : 'shopware_wins';
    }

    public function getApiVersion(?string $salesChannelId = null): string
    {
        $value = $this->systemConfigService->get(self::CONFIG_DOMAIN . 'apiVersion', $salesChannelId);

        if (!is_string($value) || trim($value) === '') {
            return 'v60.0';
        }

        $v = trim($value);
        return str_starts_with($v, 'v') ? $v : ('v' . $v);
    }

    public function isOrderSyncEnabled(?string $salesChannelId = null): bool
    {
        return (bool) $this->systemConfigService->get(self::CONFIG_DOMAIN . 'orderSyncEnabled', $salesChannelId);
    }

    public function getOrderSyncStatuses(?string $salesChannelId = null): array
    {
        $value = $this->systemConfigService->get(self::CONFIG_DOMAIN . 'orderSyncStatuses', $salesChannelId);
        if (!is_array($value) || empty($value)) {
            return [];
        }
        return array_values(array_filter($value, 'is_string'));
    }

    public function isInboundPollEnabled(?string $salesChannelId = null): bool
    {
        return (bool) $this->systemConfigService->get(self::CONFIG_DOMAIN . 'inboundPollEnabled', $salesChannelId);
    }

    public function getFieldMapping(?string $salesChannelId = null): array
    {
        $raw = $this->systemConfigService->get(self::CONFIG_DOMAIN . 'fieldMapping', $salesChannelId);

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function getRequiredString(string $key, ?string $salesChannelId): string
    {
        $value = $this->systemConfigService->get(self::CONFIG_DOMAIN . $key, $salesChannelId);

        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException(sprintf('Missing required config value: %s%s', self::CONFIG_DOMAIN, $key));
        }

        return trim($value);
    }

    public function getLastPollAt(?string $salesChannelId = null): ?\DateTimeImmutable
    {
        $value = $this->systemConfigService->get(self::CONFIG_DOMAIN . 'lastPollAt', $salesChannelId);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);

        return $dt instanceof \DateTimeImmutable ? $dt : null;
    }

    public function setLastPollAt(\DateTimeImmutable $dt, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set(
            self::CONFIG_DOMAIN . 'lastPollAt',
            $dt->format(\DateTimeInterface::ATOM),
            $salesChannelId
        );
    }
}
