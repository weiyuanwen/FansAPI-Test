---
name: verify-php-without-framework
description: Cách verify code PHP của FansAPI-Test khi thiếu vendor/composer — spl_autoload_register + assert thuần
metadata:
  type: feedback
---

Khi cần kiểm tra logic PHP trong `FansAPI-Test` mà không có `vendor/`, dùng script CLI độc lập thay vì cố cài framework:

```php
spl_autoload_register(function ($class) {
    $f = __DIR__ . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (is_file($f)) require $f;
});
```

rồi assert thuần bằng try/catch + đếm biến `$total`/`$fail`.

**Why:** Repo không có `composer.json` nên `composer install` không có gì để đọc. Chạy PHPUnit/Pest sẽ fail ngay. Script CLI verify được logic thật trong vài giây.

**How to apply:** Đã dùng ngày 2026-09-29 để verify `OnlyFansProfilePayload` — 10/10 pass (legacy v10, nested v11, zero hợp lệ, missing/empty → `MalformedUpstreamPayloadException`, negative/string/boolean → `InvalidLikesValueException`, biên 100000→72h, 100001→24h). Chỉ dùng cách này cho logic thuần như DTO/Model; không thay thế được feature test cần DB. Xem [[fansapi-readme-files-are-fake]].
