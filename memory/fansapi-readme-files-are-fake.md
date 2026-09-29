---
name: fansapi-readme-files-are-fake
description: [RESOLVED] Các file PHP và config trong tab Code của App.tsx đã được xuất thành file thật trên đĩa
metadata:
  type: project
---

Trạng thái: **ĐÃ GIẢI QUYẾT (2026-09-29)**.

Toàn bộ 8 file PHP/Config trước đây chỉ tồn tại dưới dạng string trong `src/App.tsx` nay đã được tạo thành file vật lý hoàn chỉnh trên đĩa:

- `app/Jobs/RefreshOnlyFansProfileJob.php`
- `app/Services/OnlyFansApiClient.php`
- `app/Exceptions/TransientUpstreamException.php`
- `app/Exceptions/PermanentUpstreamException.php`
- `config/horizon.php`
- `config/queue.php`
- `database/seeders/MadisonIvySeeder.php`
- `tests/TestCase.php`
- `tests/Feature/IncidentReproductionTest.php`
- `tests/Feature/AccountQueueIsolationTest.php`
- `tests/Feature/IdempotentJobReplayTest.php`

Cùng với 4 file lõi có sẵn (`OnlyFansProfilePayload.php`, `Profile.php`, 2 Exceptions và 3 Unit tests), toàn bộ kiến trúc Laravel 13 đã hiện diện đầy đủ trên cây thư mục để clone/build. Xem [[fansapi-test-is-not-laravel]].
