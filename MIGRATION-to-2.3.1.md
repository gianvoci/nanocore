# Migration Guide: → 2.3.1

## Nuove Feature

### 1. `curlRequest` — response headers in `with_info`

`curlRequest` con `'with_info' => true` ora restituisce anche la chiave `headers` nell'array, contenente tutti gli header della risposta HTTP.

**Prima**: `['body'=>mixed, 'status'=>int, 'content_type'=>string|null]`

**Dopo**: `['body'=>mixed, 'status'=>int, 'content_type'=>string|null, 'headers'=>array<string,string[]>]`

Ogni nome di header è mappato a un array di valori (per supportare header duplicati come `Set-Cookie`). I nomi preservano il casing originale della risposta.

```php
$result = NanoCore::curlRequest('https://example.com', ['with_info' => true]);
// $result['headers'] = ['Content-Type' => ['text/html'], 'Set-Cookie' => ['session=abc', 'csrf=def']]
```

**Action**: Se usi `with_info`, puoi ora accedere a `$result['headers']`. Nessuna rottura — la nuova chiave è aggiunta all'array esistente.

---

## Migration Checklist

- [ ] Nessuna action richiesta — aggiunta backward-compatible
