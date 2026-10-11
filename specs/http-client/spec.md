> Read this when you change or call `NanoCore::curlRequest()`.

# HTTP Client (`curlRequest`)

## Purpose

Static curl_multi client for single or batched outbound requests with retry, one-shot JSON body, HTTP error exceptions, JSON decoding, header collection, and masked logging. Security guards: [integrations/outbound-http.md](../integrations/outbound-http.md).

## Data model

`$options` logical keys (removed before curl; every remaining int key is a `CURLOPT_*` override):

| Key | Type | Default | Notes |
| --- | --- | --- | --- |
| `method` | `string` | `GET` | Upper-cased, sent as `CURLOPT_CUSTOMREQUEST` |
| `params` | `array\|string` | `[]` | GET: appended with `http_build_query` (`&` if URL has `?`); others: `CURLOPT_POSTFIELDS` (array → multipart, string → raw body) |
| `json` | `mixed` | none | Non-GET/HEAD: body = `json_encode($json)` (one-shot, before any handle exists). `Content-Type: application/json` is appended unless the caller already sent one (case-insensitive, whitespace-tolerant detection). Conflicts with `params` or used with `GET`/`HEAD` → `\InvalidArgumentException` before any I/O |
| `headers` | `string[]` | `[]` | `CURLOPT_HTTPHEADER` |
| `raw` | `bool` | `false` | Skip JSON decoding |
| `with_info` | `bool` | `false` | Return info array instead of body |
| `ignore_http_errors` | `bool` | `false` | `true`: HTTP ≥ 400 returns body/status like any response (pre-2.7.0 behavior) |

`with_info` result:

| Key | Type |
| --- | --- |
| `body` | `mixed` |
| `status` | `int` |
| `content_type` | `?string` |
| `headers` | `array<string, string[]>` — original casing, duplicates kept, status line excluded, last attempt only |

## Business rules

1. `string $url` → single result; failure after retries (`GET`/`HEAD` only) throws `\Exception('External request failed (cURL <code>[: reason])')` — exception code 0. The message keeps the `External request failed` prefix and cURL's own wording (e.g. `timed out`) for consumers that match on text.
2. `array $url` → list in **input order**; failed entries (transport error or HTTP error, rule 9) are `\Exception` objects, the batch continues.
3. `[]` → `[]` immediately.
4. All URLs are SSRF-validated **before** any request; one rejected URL throws for the whole batch. All resolved IPs are pinned in one `CURLOPT_RESOLVE` entry per URL (`host:port:ip[,ip...]`) — curl never re-resolves (DNS rebinding window closed); a caller `CURLOPT_RESOLVE` is always removed. Literal-IP URLs are not pinned (curl connects directly). Trailing-dot hosts (`URL host must not end with a dot`) and hostnames resolving to no address (`URL host could not be resolved`) are rejected — fail-closed. Consumers must NOT duplicate validation/pinning around `curlRequest` — it is all internal.
5. Retry only on transport errors (`CURLE_*` ≠ OK) and only for idempotent methods (`GET`, `HEAD`); other methods fail on the first transport error. Transport errors are retried regardless of `ignore_http_errors`.
6. `GET`/`HEAD` only: up to 5 attempts per URL, backoff 100/200/300/400 ms, timestamp-based (non-blocking for other handles).
7. At most 10 concurrent handles; the rest are queued.
8. Body: JSON-decoded to array when valid JSON, otherwise the raw string; `raw` returns the string; with `CURLOPT_WRITEFUNCTION` the body is whatever curl returns (content consumed by the callback).
9. **HTTP error contract (2.7.0, breaking)**: when the final status ≥ 400 and `ignore_http_errors` is not set, one `\Exception` per failed request — single URL throws, batch stores it in the result slot. Exception `code` = HTTP status; message is `External request returned HTTP <status>`, plus `: ` and the first 200 chars of the response body (line breaks stripped) when the body is non-empty. Statuses < 400 (including 3xx) are successes. `with_info` does NOT suppress the throw; only `ignore_http_errors` does.
10. Each completed response (success or HTTP error) is logged (only if a `NanoCore` was constructed, which sets the log dir): method, masked URL, status, per-request duration, masked params/`json`, response body (masked when it decodes to a JSON array, `[streamed]` with `CURLOPT_WRITEFUNCTION`) truncated to 500 chars. Transport failures are logged too: `failed after <n> attempts (cURL <code>[: reason])` with masked URL. Log lines are stripped of CR, LF, VT, FF, U+2028, and U+2029 (`LOG_LINE_BREAKS`) to a single line; a failed write falls back to `error_log()`.
11. Redirects are never followed (`CURLOPT_MAXREDIRS` is therefore inert); a caller following `Location` manually must re-validate the target URL.

## Constants

| Name | Value | Location |
| --- | --- | --- |
| Default curl options | `RETURNTRANSFER=true`, `FOLLOWLOCATION=false`, `AUTOREFERER=true`, `CONNECTTIMEOUT=30`, `TIMEOUT=30`, `MAXREDIRS=5` | `curlRequest()`, `src/NanoCore.php` ~1430 |
| `$maxRetries` | `5` | `curlRequest()` |
| `$backoffMs` | `[100, 200, 300, 400]` | `curlRequest()` |
| `$maxConcurrent` | `10` | `curlRequest()` |
| Log body cap | `500` chars | `curlRequest()` log block |
| `LOG_LINE_BREAKS` | `"\r"`, `"\n"`, `"\x0b"`, `"\x0c"`, U+2028, U+2029` | `NanoCore`, `src/NanoCore.php` ~31 |

## Cross-links

- API: [api-routes.md](../api-routes.md#http-client--ssrf-guard-static)
- Tests: `tests/cases/CurlRequestTest.php`, `tests/cases/UtilitiesTest.php`
- Migration: `MIGRATION-to-2.7.0.md`