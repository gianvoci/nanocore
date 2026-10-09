> Read this when you change outbound HTTP, URL validation, or what curl requests write to the log.

# Outbound HTTP

Contract (batch, retry, return shape): [http-client/spec.md](../http-client/spec.md).

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoCore.php` | `curlRequest()`, `validateUrlNotRestricted()`, `validateIpNotRestricted()`, `maskSensitiveParams()`, `maskUrlQuery()`, `SENSITIVE_PARAM_PATTERN`, `REDACTED_VALUE` |

## Flow

1. Normalize `$url` to a list; `[]` returns `[]`.
2. `validateUrlNotRestricted()` on every URL (throws before any I/O).
3. Merge caller `CURLOPT_*` into defaults, then force `CURLOPT_FOLLOWLOCATION=false`.
4. Build one handle per URL (params, headers, header collector); run via curl_multi, max 10 concurrent.
5. Transport failure → scheduled retry (fresh handle and header collector) or exhausted.
6. On success: log masked line to `nanocore.log`, decode body, build result.

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| Scheme allowlist | Only `http`, `https`; host required | `validateUrlNotRestricted()` |
| Internal hostnames | `localhost`, `localhost.localdomain`, `ip6-localhost`, `ip6-loopback` blocked | `validateUrlNotRestricted()` |
| Private/reserved IPs | `FILTER_FLAG_NO_PRIV_RANGE` + `FILTER_FLAG_NO_RES_RANGE` on literal IPs (IPv6 brackets stripped) | `validateIpNotRestricted()` |
| DNS resolution | A (`gethostbynamel`) + AAAA (`dns_get_record`); every resolved IP checked; unresolvable host passes to curl | `validateUrlNotRestricted()` |
| Redirects | `CURLOPT_FOLLOWLOCATION` forced `false` after caller overrides | `curlRequest()` |
| Credential logging | URL userinfo stripped; query and params keys matching `SENSITIVE_PARAM_PATTERN` → `***` (recursive); non-array body → `[raw body]` | `curlRequest()` log block, `mask*()` |
| Log size | Response body truncated to 500 chars; streamed bodies logged as `[streamed]` | `curlRequest()` |

Known limitation: DNS rebinding (TOCTOU between validation and curl's own resolution); no `CURLOPT_RESOLVE` pinning. Request headers are not logged.

## Config

| Setting | Value | Override |
| --- | --- | --- |
| `CURLOPT_CONNECTTIMEOUT` / `CURLOPT_TIMEOUT` | 30 s / 30 s | `$options[CURLOPT_*]` |
| Concurrency | 10 | none |
| Attempts / backoff | 5 / 100–400 ms | none |
| `SENSITIVE_PARAM_PATTERN` | `/pass\|secret\|token\|key\|auth\|credential/i` | `src/NanoCore.php:25` |
