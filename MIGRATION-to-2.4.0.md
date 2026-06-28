# Migration Guide: → 2.4.0

## Breaking Changes

### 1. `curlRequest` — firma cambiata da `string $url` a `string|array $url`

**Prima**: `curlRequest(string $url, array $options = []): mixed` — accettava solo una stringa URL.

**Dopo**: `curlRequest(string|array $url, array $options = []): mixed` — accetta `string` per singola richiesta o `array` di URL stringhe per batch parallelo via `curl_multi`. In batch mode, le opzioni sono condivise per tutte le URL. La signature PHP è cambiata da `string` a `string|array`.

```php
// PRIMA — solo stringa
$result = NanoCore::curlRequest('https://api.example.com/data');

// DOPO — stringa funziona ancora (backward compatibile)
$result = NanoCore::curlRequest('https://api.example.com/data');

// NUOVO — array per batch parallelo
$results = NanoCore::curlRequest([
    'https://api.example.com/users',
    'https://api.example.com/posts',
]);
// $results = [body1, body2] — in ordine di input
// Con with_info: [['body'=>..., 'status'=>200], ['body'=>..., 'status'=>200]]
```

**Action**: Nessuna modifica necessaria se passi una stringa — funziona come prima. Type checker che impongono `string` stretto potrebbero segnalare un errore con la nuova union type — aggiorna le dichiarazioni di tipo nei wrapper.

---

## Nuove Feature

### 2. Batch parallelo con `curl_multi`

`curlRequest` ora accetta un array di URL. Le richieste sono eseguite in parallelo via `curl_multi` con un massimo di 10 handle concorrenti. URL eccedenti sono accodate automaticamente (al termine di una richiesta parte la successiva). I risultati sono restituiti in un array nello stesso ordine delle URL di input.

```php
$urls = [
    'https://api.example.com/users',
    'https://api.example.com/posts',
    'https://api.example.com/comments',
];
$results = NanoCore::curlRequest($urls, ['with_info' => true]);
// $results[0] = ['body' => [...], 'status' => 200, 'content_type' => 'application/json', 'headers' => [...]]
// $results[1] = ['body' => [...], 'status' => 200, ...]
// $results[2] = ['body' => [...], 'status' => 200, ...]
```

### 3. Retry per-request non-blocking

Ogni richiesta ha fino a 5 tentativi totali (iniziale + 4 retry). Backoff lineare: 100ms, 200ms, 300ms, 400ms. Il retry è non-blocking: in batch mode, se una richiesta fallisce, l'handle viene rimosso dal multi handle, registrato per retry, e le altre richieste continuano a processare. Allo scadere del timer, l'handle viene re-inizializzato e re-aggiunto.

### 4. Batch failures gracefuli

In batch mode, se una URL fallisce dopo tutti i tentativi, l'elemento corrispondente nell'array risultati è un oggetto `Exception`. Le altre richieste non vengono abortite.

```php
$results = NanoCore::curlRequest([
    'https://httpbin.org/get',
    'http://nonexistent.invalid',  // fallirà
]);
// $results[0] = '...body...' (string)
// $results[1] = Exception('External request failed')
```

Per le singole URL (stringa singola), il comportamento resta invariato: fallimento lancia eccezione.

### 5. Array vuoto

`NanoCore::curlRequest([], $options)` ritorna `[]` immediatamente senza fare richieste HTTP.

---

## Migration Checklist

- [ ] Nessuna modifica al codice consumer richiesta — `curlRequest` con `string` funziona come prima
- [ ] Se usi type declaration nei wrapper di `curlRequest`, aggiorna da `string` a `string|array`
- [ ] Per usare il batch parallelo, passa un array di URL — i risultati sono nello stesso ordine delle URL di input
- [ ] Gestisci `Exception` nei risultati batch se alcune URL possono fallire
