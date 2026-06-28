# curlRequest Response Headers — Design

## Obiettivo

Aggiungere gli header della risposta HTTP al valore di ritorno di `curlRequest()` quando `'with_info' => true`.

## Decisioni

### 1. Header collezionati via `CURLOPT_HEADERFUNCTION`

Usiamo un callback `CURLOPT_HEADERFUNCTION` invece di `curl_getinfo()` perché:
- `curl_getinfo()` non espone header duplicati (es. `Set-Cookie`)
- Il callback processa ogni header raw preservando il casing originale
- Nessuna modifica al flusso di richiesta esistente

### 2. Formato `array<string, string[]>`

Ogni header è un array di valori per gestire header duplicati legittimi:

```php
['Content-Type' => ['application/json'], 'Set-Cookie' => ['abc', 'def']]
```

### 3. Esclusioni

- Righe `HTTP/1.1 200 OK` (status line) → saltate
- Righe vuote (fine header) → saltate
- Header malformati (nessun `:`) → saltati

### 4. Retrocompatibilità

La nuova chiave `headers` è aggiunta all'array esistente. Nessun codice esistente viene rotto.

## API

```php
NanoCore::curlRequest($url, ['with_info' => true]);
// → ['body' => mixed, 'status' => int, 'content_type' => string|null, 'headers' => array<string, string[]>]
```
