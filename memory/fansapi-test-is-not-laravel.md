---
name: fansapi-test-is-not-laravel
description: FansAPI-Test là Google AI Studio applet (React) + vài file PHP rời, không phải Laravel project
metadata:
  type: project
---

`FansAPI-Test` **không phải** Laravel project, dù README/metadata nói "Laravel 13 Incident Workbench". Thực tế trên đĩa (verify 2026-09-29):

- `src/App.tsx` — 3550 dòng, chứa toàn bộ UI workbench (5 tab: prompt / simulator / code / scaling / readme)
- `app/` — chỉ 4 file PHP: `DTOs/OnlyFansProfilePayload.php`, `Exceptions/InvalidLikesValueException.php`, `Exceptions/MalformedUpstreamPayloadException.php`, `Models/Profile.php`
- `tests/Unit/` — 3 file test PHP

Không có: `composer.json`, `artisan`, `bootstrap/app.php`, `vendor/`, `config/`, `database/`, `node_modules/`.

**Why:** Đọc README sẽ tưởng đang làm việc trên codebase Laravel đầy đủ và đi tìm những thứ không tồn tại.

**How to apply:** Đừng tìm cấu trúc Laravel chuẩn ở đây. Phần PHP thật chỉ là DTO + Model + exception, tự chứa. Xem [[fansapi-readme-files-are-fake]] và [[verify-php-without-framework]].
