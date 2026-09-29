---
name: simulator-js-diverges-from-php-dto
description: evaluateTransformations() trong App.tsx lệch logic với OnlyFansProfilePayload PHP ở case likes rỗng
metadata:
  type: project
---

`evaluateTransformations()` trong `src/App.tsx` (~dòng 646) mô phỏng lại parser PHP bằng JavaScript, nhưng lệch ở ít nhất 1 case:

**`likes: ""` (chuỗi rỗng)**
- PHP `OnlyFansProfilePayload`: `is_numeric("") === false` → throw `InvalidLikesValueException`
- JS `evaluateTransformations`: `Number("") === 0` và `isNaN(0)` là false → **chấp nhận là 0 likes hợp lệ**

Nghĩa là simulator hiển thị kết quả khác backend thật. Test `OnlyFansProfilePayloadTest::invalidLikesValueProvider` có case `'empty string' => ['', 'numeric integer']` chứng minh PHP side đúng.

Trạng thái 2026-09-29: **chưa sửa** — đã hỏi user chọn giữa sửa lệch này hay bổ sung hạ tầng test, user chưa quyết.

**Why:** Đây chính là lớp bug mà cả incident này sinh ra (null-coalescing `?? 0` nuốt mất giá trị), nên để parser JS "biết đúng hơn" backend thì phản tác dụng.

**How to apply:** Khi sửa, thêm `likesVal === ''` vào nhánh reject trong JS, và cân nhắc chạy logic JS qua bảng case chung với test PHP để tránh tách lệch lần nữa. Xem [[fansapi-test-is-not-laravel]].
