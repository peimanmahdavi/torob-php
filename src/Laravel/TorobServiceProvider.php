<?php

declare(strict_types=1);

namespace Torob\Laravel;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Torob\Client\ProductWebhookClient;
use Torob\Client\ShopApiClient;
use Torob\Client\ShopGeneratorClient;

/** Optional Laravel integration; the core package does not require Laravel. */
final class TorobServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2) . '/config/torob.php', 'torob');

        $this->app->singleton(ShopApiClient::class, static function ($app): ShopApiClient {
            $token = $app['config']->get('torob.token');
            if (!is_string($token) || $token === '') throw new InvalidArgumentException('Set TOROB_TOKEN in the application environment.');
            return new ShopApiClient($token);
        });
        $this->app->singleton(ShopGeneratorClient::class, static function ($app): ShopGeneratorClient {
            $token = $app['config']->get('torob.partner_token');
            if (!is_string($token) || $token === '') throw new InvalidArgumentException('Set TOROB_PARTNER_TOKEN in the application environment.');
            return new ShopGeneratorClient($token);
        });
        $this->app->singleton(ProductWebhookClient::class, static function ($app): ProductWebhookClient {
            $token = $app['config']->get('torob.webhook_token');
            if (!is_string($token) || $token === '') throw new InvalidArgumentException('Set TOROB_WEBHOOK_TOKEN in the application environment.');
            return new ProductWebhookClient($token);
        });
    }
}
