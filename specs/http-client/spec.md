> Read this when you change or call `NanoCore::curlRequest()`.

# HTTP Client (`curlRequest`)

## Purpose

Static curl_multi client for single or batched outbound requests with retry, JSON decoding, header collection, and masked logging. Security guards: [integrations/outbound-http.md](../integrations/outbound-http.md).

## Data model

`$options` logical keys (removed before curl; every remaining int key is a `CURLOPT_*` override):

| Key | Type | Default | Notes |
| --- | --- | --- | --- |
| `method` | `string` | `GET` | Upper-cased, sent as `CURLOPT_CUSTOMREQUEST` |
| `params` | `array\|string` | `[]` | GET: appended with `http_build_query` (`&` if URL has `?`); others: `CURLOPT_POSTFIELDS` (array → multipart, string → raw body) |
| `headers` | `string[]` | `[]` | `CURLOPT_HTTPHEADER` |
| `raw` | `bool` | `false` | Skip JSON decoding |
| `with_info` | `bool` | `false` | Return info array instead of body |

`with_info` result:

| Key | Type |
| --- | --- |
| `body` | `mixed` |
| `status` | `int` |
| `content_type` | `?string` |
| `headers` | `array<string, string[]>` — original casing, duplicates kept, status line excluded, last attempt only |

## Business rules

1. `string $url` → single result; failure after retries throws `\Exception('External request failed')`.
2. `array $url` → list in **input order**; failed entries are `\Exception` objects, the batch continues.
3. `[]` → `[]` immediately.
4. All URLs are SSRF-validated **before** any request; one rejected URL throws for the whole batch.
5. Retry only on transport errors (`CURLE_*` ≠ OK); any HTTP status (4xx/5xx) is a success result.
6. Up to 5 attempts per URL, backoff 100/200/300/400 ms, timestamp-based (non-blocking for other handles).
7. At most 10 concurrent handles; the rest are queued.
8. Body: JSON-decoded to array when valid JSON, otherwise the raw string; `raw` returns the string; with `CURLOPT_WRITEFUNCTION` the body is whatever curl returns (content consumed by the callback).
9. Each successful response is logged (only if a `NanoCore` was constructed, which sets the log dir): method, masked URL, status, per-request duration, masked params, body truncated to 500 chars or `[streamed]`. Failures are not logged.
10. Redirects are never followed (`CURLOPT_MAXREDIRS` is therefore inert).

## Constants

| Name | Value | Location |
| --- | --- | --- |
| Default curl options | `RETURNTRANSFER=true`, `FOLLOWLOCATION=false`, `AUTOREFERER=true`, `CONNECTTIMEOUT=30`, `TIMEOUT=30`, `MAXREDIRS=5` | `curlRequest()`, `src/NanoCore.php` ~1326 |
| `$maxRetries` | `5` | `curlRequest()` |
| `$backoffMs` | `[100, 200, 300, 400]` | `curlRequest()` |
| `$maxConcurrent` | `10` | `curlRequest()` |
| Log body cap | `500` chars | `curlRequest()` log block |

## Cross-links

- API: [api-routes.md](../api-routes.md#http-client--ssrf-guard-static)
- Tests: `tests/cases/CurlRequestTest.php`, `tests/cases/UtilitiesTest.php`
