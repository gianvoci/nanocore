# Migration Guide: → 2.6.0

Security hardening release. No changes to public signatures, JSON response format, or existing exception classes/messages; two new validation exceptions are introduced. Behavioral changes: non-idempotent requests are no longer retried, and SSRF validation is fail-closed for unresolvable and trailing-dot hosts.

## Behavior Changes (aligned with specs)

### 1. `curlRequest` — retries only on idempotent methods

- Transport errors (`CURLE_*`) are retried (up to 5 attempts, linear backoff 100-400 ms) only when `method` is `GET` or `HEAD`.
- `POST`, `PUT`, `DELETE`, `PATCH` now fail on the FIRST transport error: a timed-out non-idempotent request may already have been processed server-side (duplicate payments/orders), so it is never replayed.
- Outcomes are unchanged: single-URL mode still throws `\Exception("External request failed")`; batch mode still stores an `\Exception` at the failed index and continues.

**Action**: if you relied on automatic retries for non-idempotent calls, move retry logic to the caller and make the remote operation idempotent (e.g. idempotency key).

### 2. `curlRequest` — DNS rebinding window closed

- Every handle is pinned to the IPs validated upfront via `CURLOPT_RESOLVE`; curl never resolves the hostname again.
- Caller-provided `CURLOPT_RESOLVE` is ignored (same policy as `CURLOPT_FOLLOWLOCATION`).
- Literal-IP URLs are connected to directly (already validated). Hostnames that resolve to nothing are passed to curl unpinned, and the request fails exactly as before.
- `validateUrlNotRestricted()` keeps its signature and messages; callers who validate and then perform their own request are still exposed to DNS rebinding.

**Action**: none. Public hostnames resolving normally work unchanged.

### 3. `curlRequest` log — single-line entries, no throw on write failure

- The whole log line is CR/LF-stripped, so a crafted response body (or method/URL) can no longer forge additional lines in `nanocore.log`.
- If the log file cannot be written, the entry goes to `error_log()` instead of surfacing an exception.

**Action**: none.

### 4. `curlRequest` / `validateUrlNotRestricted` — fail-closed SSRF validation

- Hostnames that resolve to no address now throw `\Exception("URL host could not be resolved")` instead of being passed to curl (which failed on its own, unpinned).
- Hosts ending with a dot (e.g. `http://example.com./`) throw `\Exception("URL host must not end with a dot")` — curl's `CURLOPT_RESOLVE` cache can miss `host.` entries and silently re-resolve DNS.
- In batch mode one such URL throws for the whole batch, exactly like any restricted URL.

**Action**: none, unless you relied on unresolvable hosts passing validation — they failed anyway; only the message differs (`URL host could not be resolved` instead of `External request failed`).

---

## New Features

None — internal hardening only.

---

## Migration Checklist

- [ ] Identify `curlRequest` calls with non-idempotent methods that relied on automatic retries; move retry logic to the caller if needed
- [ ] No code relied on unresolvable or trailing-dot hosts passing validation (they now throw `URL host could not be resolved` / `URL host must not end with a dot`)
- [ ] No changes needed for `GET`/`HEAD` workloads, batches, or publicly resolving hostnames