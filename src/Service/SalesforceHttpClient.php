<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SalesforceHttpClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SalesforceTokenProvider $tokenProvider,
    ) {
    }

    public function get(string $path, ?string $salesChannelId = null): array
    {
        return $this->request('GET', $path, null, $salesChannelId);
    }

    public function post(string $path, array $json, ?string $salesChannelId = null): array
    {
        return $this->request('POST', $path, $json, $salesChannelId);
    }

    public function patch(string $path, array $json, ?string $salesChannelId = null): array
    {
        return $this->request('PATCH', $path, $json, $salesChannelId);
    }

    public function delete(string $path, ?string $salesChannelId = null): array
    {
        return $this->request('DELETE', $path, null, $salesChannelId);
    }

    private function request(string $method, string $path, ?array $json, ?string $salesChannelId): array
    {
        $token = $this->tokenProvider->getToken($salesChannelId);

        $baseUrl = rtrim($token['instanceUrl'], '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token['accessToken'],
                'Accept' => 'application/json',
            ],
        ];

        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $url, $options);

        return $this->decodeJson($response, $method, $url);
    }

    private function decodeJson(ResponseInterface $response, string $method, string $url): array
    {
        $status = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($status >= 400) {
            throw new \RuntimeException(sprintf(
                'Salesforce API error (%s %s) HTTP %d: %s',
                $method,
                $url,
                $status,
                $content
            ));
        }

        if (trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf(
                'Salesforce API returned non-JSON response (%s %s): %s',
                $method,
                $url,
                $content
            ));
        }

        return $decoded;
    }
}