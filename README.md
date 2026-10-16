# Torob PHP

کتابخانه PHP برای اتصال به APIهای ترب با استفاده از Guzzle؛ سازگار با PHP 8.1 و بالاتر و قابل استفاده در پروژه‌های مستقل PHP و Laravel.

## نصب

```bash
composer require zedx/torob-php
```

## شروع سریع

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Torob\Client\ShopApiClient;
use Torob\Exception\TorobApiException;

$torob = new ShopApiClient($_ENV['TOROB_TOKEN']);

try {
    $shop = $torob->shop('example.ir');
    print_r($shop);
} catch (TorobApiException $exception) {
    echo $exception->statusCode() . ': ' . $exception->getMessage();
}
```

## Laravel

پکیج دارای Service Provider قابل شناسایی خودکار در Laravel است. متغیرهای زیر را در `.env` تعریف کنید:

```dotenv
TOROB_TOKEN=your-shop-token
TOROB_SHOP_DOMAIN=example.ir
TOROB_PARTNER_TOKEN=your-partner-token
TOROB_WEBHOOK_TOKEN=your-webhook-token
```

تنظیمات در `config/torob.php` قابل دسترسی‌اند. کلاینت‌ها از طریق Service Container قابل تزریق هستند:

```php
use Torob\Client\ShopApiClient;

class TorobController
{
    public function showShop(ShopApiClient $torob)
    {
        return response()->json(
            $torob->shop(config('torob.shop_domain'))
        );
    }
}
```

برای دسترسی به مقادیر `.env` در کد برنامه از `config('torob.token')` استفاده کنید؛ خود کلاینت به Laravel یا فایل `.env` وابسته نیست. در برنامه‌های غیر Laravel، مقدار توکن را هنگام ساخت کلاینت به آن بدهید.

## Shop API

```php
use Torob\Client\ShopApiClient;

$torob = new ShopApiClient($token);

$shop = $torob->shop('example.ir');
$page = $torob->products(
    ['status' => 'page_not_available'],
    ['domain' => 'example.ir', 'page_size' => 100]
);

// پیمایش خودکار صفحه‌ها؛ در صورت نیاز maxPages را افزایش دهید.
$allProducts = $torob->allProducts(
    ['status' => 'page_not_available'],
    ['domain' => 'example.ir', 'page_size' => 100]
);

$torob->activateProducts(['12412_1', '12412_2'], 'example.ir');
$torob->deactivateProducts(['12412_1'], 'example.ir');
$clicks = $torob->clicks('2026-10-15', ['domain' => 'example.ir']);
$reports = $torob->priceReports(['domain' => 'example.ir']);
$torob->answerPriceReports([4521], 'claimed_to_be_corrected', 'قیمت اصلاح شد', 'example.ir');

// انتقال اعتبار برگشت‌پذیر نیست؛ ابتدا پیش‌نمایش را بررسی کنید.
$preview = $torob->previewChargeTransfer('other.ir', 'example.ir');
```

`allProducts()` صفحه‌های `next` را دنبال می‌کند و فقط URLهای HTTPS متعلق به `api.torob.com` را می‌پذیرد. برای جلوگیری از حلقه، سقف تعداد صفحه‌ها دارد. درخواست‌های Shop API در هر فرایند PHP به‌صورت best-effort با فاصله‌ی ۲۰۰ میلی‌ثانیه محدود می‌شوند؛ در اجرای چند worker یا چند سرور، rate limit سراسری همچنان باید در سطح برنامه مدیریت شود. در انتقال اعتبار، برای retry همان `request_id` را استفاده کنید؛ برای انتقال جدید شناسه جدید بسازید.

## Shop Generator و Product Webhook

```php
use Torob\Client\ShopGeneratorClient;
use Torob\Client\ProductWebhookClient;

$generator = new ShopGeneratorClient($partnerToken);
$connection = $generator->requestConnection(
    'example.ir',
    'https://platform.example.ir/torob/return'
);

$webhook = new ProductWebhookClient($webhookToken);
$webhook->notify([
    [
        'page_url' => 'https://example.ir/product/123',
        'page_unique' => 'product-123',
    ],
]);
```

در هر درخواست webhook تعداد محصولات باید بین ۱ تا ۱۰۰ باشد و همه URLها باید از یک دامنه و با پروتکل HTTP یا HTTPS باشند.

## پیاده‌سازی endpoint سمت فروشگاه: Product API v3

در این بخش ترب به endpoint فروشگاه درخواست می‌فرستد. پکیج منطق احراز هویت، اعتبارسنجی ورودی و قالب پاسخ را فراهم می‌کند؛ اتصال به دیتابیس باید با پیاده‌سازی `ProductRepository` انجام شود. برای cursor pagination، repository باید علاوه بر آن `CursorProductRepository` را پیاده‌سازی کند.

```php
use Torob\ProductSync\ProductApiV3Service;
use Torob\ProductSync\ProductRepository;
use InvalidArgumentException;
use RuntimeException;

// $repository باید ProductRepository پیاده‌سازی‌شده توسط برنامه باشد.
$service = new ProductApiV3Service($repository, $request->getHttpHost());

try {
    $service->authenticate(
        $request->header('X-Torob-Token'),
        $request->header('X-Torob-Token-Version')
    );
    return response()->json($service->handle($request->json()->all()));
} catch (InvalidArgumentException $exception) {
    return response()->json(['error' => $exception->getMessage()], 400);
} catch (RuntimeException $exception) {
    $status = in_array($exception->getCode(), [401, 403], true) ? $exception->getCode() : 500;
    return response()->json(['error' => $exception->getMessage()], $status);
}
```

endpoint را خود برنامه در مسیری که برای ترب ثبت شده است تعریف می‌کند؛ این کتابخانه به‌تنهایی route یا کنترلر Laravel ثبت نمی‌کند. `audience` باید دقیقاً با Host درخواست، شامل پورت در صورت وجود، برابر باشد. در cursor mode، درخواست اول با `sort=product_id_desc` و بدون `page` ارسال می‌شود و مقدار `next_cursor` باید بدون تغییر در درخواست بعدی استفاده شود.

محصولات باید فیلدهای اجباری مستندات ترب را داشته باشند؛ از جمله `page_unique`، `page_url`، `title`، `current_price`، `availability`، `image_links`، `spec` و `date_added`. تاریخ‌ها باید ISO 8601 همراه timezone باشند.

## رهگیری سفارش و Action

پکیج سرویس‌های framework-neutral برای endpointهای `GET /torob/v1/orders` و `GET /torob/v1/actions` دارد. برای استفاده باید repository مربوطه را به دیتابیس فروشگاه متصل و endpointها را در برنامه ثبت کنید.

```php
use Torob\Tracking\OrderTrackingService;
use Torob\Tracking\ActionTrackingService;

// $orderRepository باید Torob\Tracking\OrderRepository باشد.
$orders = new OrderTrackingService($orderRepository, enabled: true);
$result = $orders->handle(
    $request->query(),
    $request->header('X-Torob-Token'),
    $request->header('X-Torob-Token-Version'),
    $request->getHttpHost()
);
```

برای Action Tracking از `ActionTrackingService` و `ActionRepository` استفاده کنید. repository باید فقط رکوردهای منتسب به ترب را برگرداند و بر اساس timestamp مرتب کند. سرویس‌ها JWT، نسخه توکن، `aud`، بازه زمانی، سقف `limit` و قالب پاسخ را بررسی می‌کنند. برای فروشگاه‌هایی که این قابلیت را غیرفعال کرده‌اند، سرویس را با `enabled: false` بسازید.

`torob_clid` را هنگام ورود کاربر از query string بگیرید، به session/cookie امن متصل کنید و هنگام ثبت سفارش یا action در دیتابیس ذخیره کنید. این بخش به ساختار session و سفارش‌های برنامه وابسته است.

## APIهای قدیمی

`ProductApiV1Service` و `ProductApiV2Service` برای سازگاری با نسخه‌های قدیمی مستندات ترب ارائه شده‌اند و از `LegacyProductRepository` استفاده می‌کنند. هر دو نسخه منسوخ شده‌اند؛ برای یکپارچه‌سازی جدید، Product API v3 پیشنهاد می‌شود. v2 را فقط در صورت نیاز مشخص از سمت ترب فعال کنید.

## خطاها

در کلاینت‌های خروجی، خطاهای HTTP و پاسخ ترب با `TorobApiException` گزارش می‌شوند:

```php
try {
    $products = $torob->products([], ['domain' => 'example.ir']);
} catch (\Torob\Exception\TorobApiException $exception) {
    $status = $exception->statusCode();
    $body = $exception->responseBody();
}
```

## تست‌ها

```bash
composer install
composer test
composer audit
```

## مستندات رسمی

- [Torob-Sync](https://github.com/Torob/Torob-Sync)
- [Shop API](https://github.com/Torob/Torob-Sync/blob/main/shop_api.md)
- [Product API v3](https://github.com/Torob/Torob-Sync/blob/main/product_api_v3.md)
- [Product Webhook](https://github.com/Torob/Torob-Sync/blob/main/product_webhook.md)
- [Order Tracking](https://github.com/Torob/Torob-Sync/blob/main/order_tracking_api.md)
- [Action Tracking](https://github.com/Torob/Torob-Sync/blob/main/action_tracking_api.md)
