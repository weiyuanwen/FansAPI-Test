---
name: simulator-js-diverges-from-php-dto
description: [RESOLVED] evaluateTransformations() trong App.tsx đã được đồng bộ với OnlyFansProfilePayload PHP bằng hàm isNumericLike()
metadata:
  type: project
---

Trạng thái: **ĐÃ GIẢI QUYẾT (2026-09-29)**.

`evaluateTransformations()` trong `src/App.tsx` đã được đồng bộ hoàn toàn với PHP:
- Bổ sung helper `isNumericLike(value: unknown): boolean` mô phỏng chính xác hàm `is_numeric()` của PHP: từ chối boolean, null, array, object, và chuỗi rỗng `""`.
- Khi upstream gửi `likes: ""`, JS parser ném `InvalidLikesValueException` đúng chuẩn backend DTO thay vì nuốt thành 0.
- Đã thêm preset `empty_string_likes` vào sub-simulator trong UI để người dùng và developer kiểm thử trực tiếp. Xem [[fansapi-test-is-not-laravel]].
