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
    protected readonly string $baseUri;
    private static array $lastShopApiRequestAt = [];

    public function __construct(
        protected readonly string $token,
        string $baseUri,
        array $options = [],
        ?ClientInterface $http = null,
    ) {
        $this->baseUri = rtrim($baseUri, '/') . '/';
        $this->http = $http ?? new Client(array_replace_recursive([
            'base_uri' => $this->baseUri,
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
            if (str_contains($this->baseUri, '/shop-api/v1/')) {
                $this->throttleShopApi($uri, $options);
            }
            $requestUri = preg_match('~^https?://~i', $uri) === 1 ? $uri : ltrim($uri, '/');
            $response = $this->http->request($method, $requestUri, $options);
        } catch (RequestException $e) {
            throw new TorobApiException('Torob request failed: ' . $e->getMessage(), 0, [], $e);
        } catch (\Throwable $e) {
            throw new TorobApiException('Torob request failed: ' . $e->getMessage(), 0, [], $e);
        }

        return $this->decodeResponse($response);
    }

    /** Best-effort 5 requests/second pacing per token and shop within this PHP process. */
    private function throttleShopApi(string $uri, array $options): void
    {
        $domain = $options['query']['domain'] ?? null;
        if (!is_string($domain) || $domain === '') {
            $queryString = parse_url($uri, PHP_URL_QUERY);
            if (is_string($queryString)) {
                parse_str($queryString, $query);
                $domain = $query['domain'] ?? '';
            } else {
                $domain = '';
            }
        }
        $key = hash('sha256', $this->token . '|' . $domain);
        $now = hrtime(true);
        $minimumInterval = 200_000_000; // 200 ms = at most 5 starts per second.
        $last = self::$lastShopApiRequestAt[$key] ?? null;
        if (is_int($last) && ($now - $last) < $minimumInterval) {
            usleep((int) ceil(($minimumInterval - ($now - $last)) / 1000));
        }
        self::$lastShopApiRequestAt[$key] = hrtime(true);
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
