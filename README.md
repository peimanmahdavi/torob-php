# کتابخانه PHP ترب (Torob)

کتابخانه‌ای برای یکپارچه‌سازی پروژه‌های PHP با APIهای ترب، با استفاده از Guzzle و پشتیبانی از PHP 8.1 به بالا.

> **وضعیت پروژه:** این کتابخانه در حال توسعه است. کلاینت‌های Shop API، Shop Generator و Product Webhook پیاده‌سازی اولیه دارند. سرویس Product API v3 و ابزارهای رهگیری هنوز برای استفاده‌ی کامل در محیط عملیاتی به توسعه و آزمون بیشتری نیاز دارند.

## قابلیت‌ها

| بخش | قابلیت‌ها | وضعیت |
|---|---|---|
| Shop API | اطلاعات فروشگاه، فهرست محصولات، فعال‌سازی و غیرفعال‌سازی، کلیک‌ها، گزارش قیمت و انتقال اعتبار | پیاده‌سازی اولیه |
| Shop Generator | درخواست اتصال، بررسی وضعیت، تأیید و قطع اتصال فروشگاه | پیاده‌سازی اولیه |
| Product Webhook | ارسال اعلان تغییر محصول به ترب | پیاده‌سازی اولیه |
| Product API v3 | سرویس سمت فروشگاه و قرارداد منبع محصولات | نیازمند تکمیل اتصال به داده‌ها و صفحه‌بندی |
| رهگیری کلیک و سفارش | ابزار انتساب کلیک و زیرساخت رهگیری | نیازمند تکمیل endpointها |
| Product API v1/v2 | سازگاری با نسخه‌های قدیمی | پیاده‌سازی نشده |

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

$client = new ShopApiClient($_ENV['TOROB_TOKEN']);

try {
    $shop = $client->shop('example.ir');
    print_r($shop);
} catch (TorobApiException $e) {
    printf(
        "Torob API error (HTTP %d): %s\n",
        $e->statusCode(),
        $e->getMessage()
    );
}
```

توکن را از تنظیمات امن برنامه بخوانید و آن را در کد یا مخزن Git قرار ندهید. نام دامنه و توکن نمونه را با مقادیر واقعی جایگزین کنید.

## استفاده از Shop API

### دریافت اطلاعات فروشگاه

```php
$shop = $client->shop('example.ir');
```

### دریافت محصولات

```php
$products = $client->products(
    ['status' => 'page_not_available'],
    ['domain' => 'example.ir', 'page_size' => 100]
);
```

در پیاده‌سازی فعلی، پیمایش خودکار تمام صفحه‌ها وجود ندارد. اگر پاسخ فیلد `next` داشته باشد، صفحه‌ی بعدی را جداگانه دریافت کنید.

### فعال یا غیرفعال‌کردن محصولات

```php
$client->activateProducts(['12412_1', '12412_2'], 'example.ir');
$client->deactivateProducts(['12412_1'], 'example.ir');
```

طبق مستندات API، هر درخواست می‌تواند حداکثر ۵۰۰۰ شناسه داشته باشد؛ فهرست‌های بزرگ‌تر را به چند درخواست تقسیم کنید.

### دریافت کلیک‌ها و گزارش قیمت

```php
$clicks = $client->clicks('2026-10-15', [
    'domain' => 'example.ir',
    'page' => 1,
    'page_size' => 100,
    'click_type' => 'normal',
]);

$reports = $client->priceReports([
    'domain' => 'example.ir',
    'active_reports' => 'active',
    'page' => 1,
]);
```

### بررسی انتقال اعتبار

```php
$preview = $client->previewChargeTransfer('other.ir', 'example.ir');
```

انتقال اعتبار عملیاتی حساس است. پیش از انجام انتقال، نتیجه‌ی بررسی اولیه را کنترل کنید و برای ارسال دوباره‌ی درخواست، رفتار `request_id` و تکرارپذیری را مطابق مستندات ترب در نظر بگیرید.

## Shop Generator

این کلاینت برای پلتفرم‌هایی در نظر گرفته شده که اتصال فروشگاه‌ها به ترب را مدیریت می‌کنند.

```php
use Torob\Client\ShopGeneratorClient;

$generator = new ShopGeneratorClient($_ENV['TOROB_PARTNER_TOKEN']);

$connection = $generator->requestConnection(
    'example.ir',
    'https://platform.example.ir/torob/callback'
);
```

فرایند اتصال را به کاربر یا نشست آغازکننده مرتبط کنید و هنگام callback مطمئن شوید همان فرایند در حال تأیید است.

## Product Webhook

برای اعلام تغییر محصول به ترب:

```php
use Torob\Client\ProductWebhookClient;

$webhook = new ProductWebhookClient($_ENV['TOROB_WEBHOOK_TOKEN']);

$result = $webhook->notify([
    [
        'page_url' => 'https://example.ir/product/34/',
        'page_unique' => '12412_1',
    ],
]);
```

هر batch طبق مستندات فعلی حداکثر ۱۰۰ محصول می‌پذیرد. پاسخ موفق نشان می‌دهد اعلان دریافت شده است و لزوماً به معنی پایان پردازش محصول در ترب نیست.

## Product API v3

در این API، ترب به endpoint پیاده‌سازی‌شده در سمت فروشگاه درخواست می‌فرستد. پکیج کلاس اولیه‌ی `ProductApiV3Service` و قرارداد `ProductRepository` را فراهم می‌کند؛ اتصال به منبع داده‌ی واقعی، اعتبارسنجی کامل فیلدهای محصول و cursor pagination هنوز باید تکمیل شوند.

برای اتصال به Laravel می‌توانید `ProductRepository` را با Eloquent پیاده‌سازی کنید؛ در پروژه‌های مستقل PHP نیز PDO گزینه‌ی مناسبی است.

## رهگیری کلیک و سفارش

کلاس `TorobClickAttribution` امکان دریافت شناسه‌ی کلیک از ورودی را فراهم می‌کند:

```php
use Torob\Tracking\TorobClickAttribution;

$clickId = TorobClickAttribution::capture($_GET);

if ($clickId !== null) {
    // شناسه را به‌شکل امن نگه‌داری و در صورت ثبت سفارش به آن مرتبط کنید.
}
```

این helper به‌تنهایی endpointهای Order Tracking و Action Tracking را پیاده‌سازی نمی‌کند. برای استفاده‌ی کامل، endpointها، احراز هویت JWT، مجوزهای هر فروشگاه و قالب پاسخ باید مطابق مستندات ترب تکمیل شوند.

## مدیریت خطا

خطاهای HTTP از طریق `TorobApiException` در دسترس هستند:

```php
try {
    $shop = $client->shop('example.ir');
} catch (TorobApiException $e) {
    $status = $e->statusCode();
    $responseBody = $e->responseBody();

    // توکن‌ها و اطلاعات حساس را در log ثبت نکنید.
}
```

در مدیریت خطا، به‌ویژه برای پاسخ‌های `401`، `403` و `429`، رفتار احراز هویت و محدودیت درخواست را در نظر بگیرید. درخواست‌های مالی را بدون بررسی سازوکار جلوگیری از اجرای تکراری دوباره ارسال نکنید.

## تست‌ها

```bash
composer install
composer test
```

تست‌های موجود پوشش کامل endpointها را تضمین نمی‌کنند. پیش از استفاده‌ی عملیاتی، مسیرهای موفق و ناموفق، احراز هویت، صفحه‌بندی و payloadهای نامعتبر را آزمایش کنید.

## مستندات رسمی

- [مخزن Torob-Sync](https://github.com/Torob/Torob-Sync)
- [Shop API](https://github.com/Torob/Torob-Sync/blob/main/shop_api.md)
- [Shop Generator Connection](https://github.com/Torob/Torob-Sync/blob/main/shop_generator_connection.md)
- [Product API v3](https://github.com/Torob/Torob-Sync/blob/main/product_api_v3.md)
- [راهنمای توکن و JWT](https://github.com/Torob/Torob-Sync/blob/main/torob_api_token_guide.md)
- [Product Webhook](https://github.com/Torob/Torob-Sync/blob/main/product_webhook.md)
- [Order Tracking](https://github.com/Torob/Torob-Sync/blob/main/order_tracking_api.md)
- [Action Tracking](https://github.com/Torob/Torob-Sync/blob/main/action_tracking_api.md)

## مجوز

این پروژه تحت مجوز MIT منتشر می‌شود. متن کامل مجوز در فایل [LICENSE](LICENSE) قرار دارد. این مجوز مربوط به کد این کتابخانه است و جایگزین شرایط استفاده از APIهای ترب نمی‌شود.
