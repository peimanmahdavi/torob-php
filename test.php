<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Torob\Client\ShopApiClient;
use Torob\Exception\TorobApiException;

$token = getenv('TOROB_TOKEN');
$domain = getenv('TOROB_DOMAIN') ?: null;

if (!$token) {
    fwrite(STDERR, "Error: TOROB_TOKEN environment variable is not set.\n\n");
    fwrite(STDERR, "Linux/macOS:\n  export TOROB_TOKEN='your-torob-token'\n  export TOROB_DOMAIN='example.com' # optional\n  php test.php\n\n");
    fwrite(STDERR, "Windows PowerShell:\n  $env:TOROB_TOKEN='your-torob-token'\n  $env:TOROB_DOMAIN='example.com' # optional\n  php test.php\n");
    exit(1);
}

try {
    $client = new ShopApiClient($token);

    echo "Testing Torob Shop API...\n";
    echo $domain ? "Shop domain: {$domain}\n\n" : "Shop domain: not specified\n\n";

    // Read-only request: this does not activate/deactivate products or transfer credit.
    $result = $client->shop($domain);

    echo "Request succeeded. API response:\n";
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} catch (TorobApiException $e) {
    fwrite(STDERR, "Torob API request failed.\n");
    fwrite(STDERR, "HTTP status: " . ($e->statusCode() ?: 'n/a') . "\n");
    fwrite(STDERR, "Message: {$e->getMessage()}\n");

    $response = $e->responseBody();
    if ($response !== []) {
        fwrite(STDERR, "Response body:\n");
        fwrite(STDERR, json_encode(
            $response,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL);
    }

    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Unexpected error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
