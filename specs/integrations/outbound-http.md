> Read this when you change outbound HTTP, URL validation, or what curl requests write to the log.

# Outbound HTTP

Contract (batch, retry, return shape): [http-client/spec.md](../http-client/spec.md).

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoCore.php` | `curlRequest()`, `validateUrlNotRestricted()`, `validateIpNotRestricted()`, `maskSensitiveParams()`, `maskUrlQuery()`, `SENSITIVE_PARAM_PATTERN`, `REDACTED_VALUE` |

## Flow

1. Normalize `$url` to a list; `[]` returns `[]`.
2. `resolveSsrfPins()` on every URL (throws before any I/O): SSRF validation + `CURLOPT_RESOLVE` pin lines per URL.
3. Merge caller `CURLOPT_*` into defaults, then force `CURLOPT_FOLLOWLOCATION=false`.
4. Build one handle per URL (params, `json` body, headers, header collector, `CURLOPT_RESOLVE` pins); run via curl_multi, max 10 concurrent.
5. Transport failure → scheduled retry (fresh handle and header collector) or exhausted; retries only for `GET`/`HEAD`; failure message carries the cURL code/reason (`External request failed (cURL ...)`, exception code 0).
6. On completion: log masked line to `nanocore.log` (successes, HTTP errors, and transport failures), decode body, build result. HTTP ≥ 400 → `\Exception` (code = HTTP status) unless `ignore_http_errors`; single URL throws, batch stores it in the slot.

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| Scheme allowlist | Only `http`, `https`; host required | `validateUrlNotRestricted()` |
| Internal hostnames | `localhost`, `localhost.localdomain`, `ip6-localhost`, `ip6-loopback` blocked | `validateUrlNotRestricted()` |
| Private/reserved IPs | `FILTER_FLAG_NO_PRIV_RANGE` + `FILTER_FLAG_NO_RES_RANGE` on literal IPs (IPv6 brackets stripped) | `validateIpNotRestricted()` |
| DNS resolution | A (`gethostbynamel`) + AAAA (`dns_get_record`); every resolved IP checked and pinned; unresolvable hostnames rejected fail-closed (`URL host could not be resolved`) | `resolveSsrfPins()` (also used by `validateUrlNotRestricted()`) |
| Trailing-dot hosts | Rejected (`URL host must not end with a dot`) — curl's `CURLOPT_RESOLVE` cache can miss `host.` entries and silently re-resolve DNS | `resolveSsrfPins()` |
| IP pinning | One `CURLOPT_RESOLVE` entry per URL (`host:port:ip[,ip...]`, all validated IPs, IPv6 bracketed) — curl never re-resolves (DNS rebinding TOCTOU closed); caller `CURLOPT_RESOLVE` always removed; literal-IP hosts unpinned | `curlRequest()` |
| Redirects | `CURLOPT_FOLLOWLOCATION` forced `false` after caller overrides | `curlRequest()` |
| Credential logging | URL userinfo stripped; query and params keys matching `SENSITIVE_PARAM_PATTERN` → `***` (recursive); non-array body → `[raw body]`; `json` option masked on its decoded form, non-array → `[json body]`; response bodies decoding to a JSON array masked before logging | `curlRequest()` log block, `mask*()` |
| Log size | Response body truncated to 500 chars; streamed bodies logged as `[streamed]` | `curlRequest()` |
| Log line forging | Whole log line stripped of CR, LF, VT, FF, U+2028, U+2029 (`LOG_LINE_BREAKS`, single line); the HTTP-error body snippet in exception messages is stripped the same way; failed write falls back to `error_log()` instead of throwing | `curlRequest()` log block, `appendLogLine()` |
| Content-Type injection | With the `json` option, `Content-Type: application/json` is appended only when the caller sent no Content-Type header (case-insensitive, whitespace-tolerant) — no duplicate headers | `curlRequest()`, `headersContainContentType()` |

Standalone `validateUrlNotRestricted()` callers that perform the request themselves are still exposed to DNS rebinding (pinning applies inside `curlRequest()` only). Request headers are not logged. Consumers must not duplicate validation/pinning around `curlRequest()` calls — it validates and pins internally and discards caller `CURLOPT_RESOLVE`.

## Config

| Setting | Value | Override |
| --- | --- | --- |
| `CURLOPT_CONNECTTIMEOUT` / `CURLOPT_TIMEOUT` | 30 s / 30 s | `$options[CURLOPT_*]` |
| Concurrency | 10 | none |
| Attempts / backoff | 5 / 100–400 ms | none |
| `SENSITIVE_PARAM_PATTERN` | `/pass\|secret\|token\|key\|auth\|credential/i` | `src/NanoCore.php:25` |
| IP pinning | One `CURLOPT_RESOLVE` entry per URL: `host:port:ip[,ip...]` (all validated A/AAAA IPs) | none (caller `CURLOPT_RESOLVE` removed) |
