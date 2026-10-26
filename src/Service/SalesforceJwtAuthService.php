<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SalesforceJwtAuthService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigProvider $config
    ) {
    }

    public function fetchAccessToken(?string $salesChannelId = null): array
    {
        $aud = rtrim($this->config->getLoginUrl($salesChannelId), '/');
        $tokenUrl = $aud . '/services/oauth2/token';

        $clientId = $this->config->getClientId($salesChannelId);
        $username = $this->config->getUsername($salesChannelId);

        $jwt = $this->buildJwtAssertion(
            clientId: $clientId,
            username: $username,
            aud: $aud,
            privateKeyPem: $this->config->getJwtPrivateKeyPem($salesChannelId)
        );

        $response = $this->httpClient->request('POST', $tokenUrl, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
        ]);

        $status = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($status >= 400) {
            throw new \RuntimeException(sprintf('Salesforce JWT token error HTTP %d: %s', $status, $content));
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['access_token'], $decoded['instance_url'])) {
            throw new \RuntimeException(sprintf('Unexpected Salesforce token response: %s', $content));
        }

        return $decoded;
    }

    private function buildJwtAssertion(string $clientId, string $username, string $aud, string $privateKeyPem): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $now = time();
        $payload = [
            'iss' => $clientId,
            'sub' => $username,
            'aud' => $aud,
            'exp' => $now + 180,
            'iat' => $now,
        ];

        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $segments[] = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signingInput = implode('.', $segments);

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new \RuntimeException('Invalid private key PEM (openssl_pkey_get_private failed).');
        }

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if ($ok !== true) {
            throw new \RuntimeException('Failed to sign JWT (openssl_sign failed).');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}