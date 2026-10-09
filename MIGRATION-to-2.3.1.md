# Migration Guide: → 2.3.1

## New Features

### 1. `curlRequest` — response headers in `with_info`

`curlRequest` with `'with_info' => true` now also returns the `headers` key in the array, containing all headers of the HTTP response.

**Before**: `['body'=>mixed, 'status'=>int, 'content_type'=>string|null]`

**After**: `['body'=>mixed, 'status'=>int, 'content_type'=>string|null, 'headers'=>array<string,string[]>]`

Each header name is mapped to an array of values (to support duplicate headers such as `Set-Cookie`). The names preserve the original casing of the response.

```php
$result = NanoCore::curlRequest('https://example.com', ['with_info' => true]);
// $result['headers'] = ['Content-Type' => ['text/html'], 'Set-Cookie' => ['session=abc', 'csrf=def']]
```

**Action**: If you use `with_info`, you can now access `$result['headers']`. No breaking change — the new key is added to the existing array.

---

## Migration Checklist

- [ ] No action required — backward-compatible addition
