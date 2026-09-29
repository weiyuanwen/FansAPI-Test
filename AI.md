# AI Usage, Verification & Audit Log

> Required submission file for Engineer @FansAPI

---

## 1. Tools & Models Used
- **Primary Model**: Google AI Studio / Gemini 2.5 / 3.0 Pro & Claude Code
- **Laravel Ecosystem Documentation**: Laravel 13 Release Notes, Laravel Horizon docs, Redis Queue Internals, Laravel Scout documentation.
- **Local Verification Environment**: PHP 8.3 / 8.4 CLI, SQLite in-memory test runner, Redis 7.2.

---

## 2. What Was Verified
1. **Response Parser & Schema Drift (`OnlyFansProfilePayload`)**:
   - Verified that `array_key_exists('likes', ...)` is necessary instead of `isset(...)` or `??` so that numeric `0` is recognized as valid rather than rejected.
   - Verified that negative likes (`-1`) and non-numeric strings are rejected with typed exceptions (`InvalidLikesValueException`).
   - Verified that missing keys throw `MalformedUpstreamPayloadException` instead of falling back to default values.

2. **Database Monotonic Revision Protection**:
   - Verified that `UPDATE profiles ... WHERE revision < :inbound_revision` atomic SQL query prevents out-of-order responses from regressing data.
   - Tested scenario where revision 10 arrives 2 seconds after revision 11: database successfully preserved revision 11 without throwing application errors.

3. **Queue Timeout Harmony**:
   - Verified mathematically and practically:
     $$\text{Redis } retry\_after \ (90s) > \text{Worker } timeout \ (60s) > \text{Job } timeout \ (30s)$$
   - Verified that if `retry_after <= worker_timeout`, Redis will re-deliver the job to another worker process while the first worker is still active, causing double-execution.

4. **Account Queue Isolation**:
   - Simulated 50 queued jobs for Account A (bursting HTTP 429) and 10 queued jobs for Account B (HTTP 200).
   - Verified that Account B finished in < 2 seconds while Account A jobs were delayed by the jitter backoff policy without starving Account B.

---

## 3. Incorrect Suggestions Caught & Rectified
1. **Incorrect Null Coalescing on `0` Likes**:
   - *AI Initial Suggestion:* `$likes = (int) ($data['profile']['likes'] ?? $data['likes'] ?? 0);`
   - *Problem Caught:* If upstream sends `{"profile": {}}` or missing `likes`, this would still silently default to `0`, re-introducing the exact production bug!
   - *Fix Implemented:* Explicitly check for key existence. If neither is present, throw a fatal `MalformedUpstreamPayloadException`.

2. **Flawed Cadence Boundary**:
   - *AI Initial Suggestion:* `$interval = $likes >= 100000 ? 24 : 72;`
   - *Problem Caught:* The specification explicitly states: *"Refresh profiles above 100,000 likes every 24 hours and all others every 72 hours. Exactly 100,000 belongs to the 72-hour group."*
   - *Fix Implemented:* Changed condition to `$likes > 100000 ? 24 : 72;` so that 100,000 is correctly assigned to the 72-hour group.

3. **Naive Synchronous Sleep on 429**:
   - *AI Initial Suggestion:* `sleep(rand(5, 10))` inside `handle()`.
   - *Problem Caught:* Sleeping synchronously inside the worker blocks the PHP process from doing any other work, exhausting the Horizon worker pool.
   - *Fix Implemented:* Used `$this->release($jitterDelay)` to return the job to Redis with a randomized delay, freeing the worker process immediately.

---

## 4. What Has NOT Been Verified in Production
1. **Real OnlyFans API Cloudflare / Akamai Bot Mitigation**:
   - In production, OnlyFans uses Cloudflare bot management and signature algorithms (Dynamic Rules / Headers). This local implementation assumes an authenticated API token and reverse-proxy egress mesh, which cannot be tested without production credentials.
2. **True 50 Million Jobs/Day Live Workload**:
   - Simulated locally with high-density unit and feature tests. A live 50M jobs/day deployment requires a multi-node Redis cluster, distributed worker nodes across Kubernetes, and proxy IP rotation pools.
