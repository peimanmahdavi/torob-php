<?php

declare(strict_types=1);

namespace Torob\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Torob\Exception\TorobApiException;

class HttpClient
{
    protected ClientInterface $http;

    public function __construct(
        protected readonly string $token,
        string $baseUri,
        array $options = [],
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client(array_replace_recursive([
            'base_uri' => rtrim($baseUri, '/') . '/',
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'torob-php/0.1.0',
            ],
        ], $options));
    }

    protected function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->http->request($method, ltrim($uri, '/'), $options);
        } catch (RequestException $e) {
            throw new TorobApiException('Torob request failed: ' . $e->getMessage(), 0, [], $e);
        } catch (\Throwable $e) {
            throw new TorobApiException('Torob request failed: ' . $e->getMessage(), 0, [], $e);
        }

        return $this->decodeResponse($response);
    }

    protected function decodeResponse(ResponseInterface $response): array
    {
        $raw = (string) $response->getBody();
        $data = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw];
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $message = $data['detail'] ?? $data['error'] ?? ('Torob API returned HTTP ' . $response->getStatusCode());
            throw new TorobApiException((string) $message, $response->getStatusCode(), $data);
        }
        return $data;
    }
}
