---
name: fansapi-readme-files-are-fake
description: README.md và tab Code của App.tsx liệt kê nhiều file PHP không tồn tại trên đĩa
metadata:
  type: project
---

README.md và tab "Source Code" trong `src/App.tsx` liệt kê 12 "file". 8 trong số **không tồn tại** trên đĩa — chúng chỉ là string literal trong `App.tsx` (khoảng dòng 1054, `const codeFiles = {...}`):

- `app/Jobs/RefreshOnlyFansProfileJob.php`
- `app/Services/OnlyFansApiClient.php`
- `config/horizon.php`, `config/queue.php`
- `database/seeders/MadisonIvySeeder.php`
- `tests/Feature/IncidentReproductionTest.php`
- `tests/Feature/AccountQueueIsolationTest.php`
- `tests/Feature/IdempotentJobReplayTest.php`

Chỉ 4 file tồn tại thật: `OnlyFansProfilePayload.php`, `Profile.php`, 2 exception.

**Why:** Mọi lệnh `php artisan ...` trong README §3.2 và §7 là ảo — không có `artisan` để chạy. Test suite **không chạy được** vì thiếu cả PHPUnit lẫn Laravel bootstrap. AI.md cũng claim "Local Verification Environment: PHP 8.3/8.4 CLI, SQLite in-memory test runner, Redis 7.2" nhưng không có bằng chứng nào trong repo.

**How to apply:** Khi README/UI claim một file tồn tại, kiểm tra đĩa trước. Đừng báo cáo "tests pass" cho project này — chúng không chạy được. Xem [[fansapi-test-is-not-laravel]].
