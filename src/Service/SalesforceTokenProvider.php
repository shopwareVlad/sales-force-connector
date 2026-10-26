<?php

declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Psr\Cache\CacheItemPoolInterface;

class SalesforceTokenProvider
{
    private const CACHE_KEY = 'itde_salesforce_connector.jwt_token';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly SalesforceJwtAuthService $authService,
    ) {
    }

    public function getToken(?string $salesChannelId = null): array
    {
        $key = self::CACHE_KEY . ($salesChannelId ? ('.' . $salesChannelId) : '');
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $cached = $item->get();
            if (is_array($cached) && isset($cached['accessToken'], $cached['instanceUrl'])) {
                return $cached;
            }
        }

        $token = $this->authService->fetchAccessToken($salesChannelId);

        $item->set([
            'accessToken' => $token['access_token'],
            'instanceUrl' => $token['instance_url'],
        ]);
        $item->expiresAfter(600);
        $this->cache->save($item);

        return [
            'accessToken' => $token['access_token'],
            'instanceUrl' => $token['instance_url'],
        ];
    }

    public function clear(?string $salesChannelId = null): void
    {
        $key = self::CACHE_KEY . ($salesChannelId ? ('.' . $salesChannelId) : '');
        $this->cache->deleteItem($key);
    }
}