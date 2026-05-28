# Migration Guide: → 2.2.4

## Breaking Changes

### 1. JSON error key unificato: `message` → `error`

`sendJsonError()` (usato sia dal global exception handler che da `run()`) ora usa la chiave `"error"` nel JSON response, coerentemente. In precedenza, il global handler usava `"message"` mentre `run()` usava `"error"`.

**Se il tuo client parsava `response.message`**, ora deve parsare `response.error`:

```php
// BEFORE (2.2.2) — global handler usava 'message'
$error = $json['message'];

// AFTER (2.2.3) — sempre 'error'
$error = $json['error'];
```

**Action**: Cerca `$json['message']` o `response.message` nel parsing delle risposte di errore e sostituisci con `error`.

### 2. `__set` su NanoORM ora lancia eccezione per campi sconosciuti

Prima, assegnare un valore a un campo inesistente in un ORM veniva silenziosamente ignorato. Ora lancia `\InvalidArgumentException`.

```php
// BEFORE (2.2.2) — silenziosamente ignorato
$user->campo_inesistente = 'valore'; // nessun errore, il dato non veniva salvato

// AFTER (2.2.3) — eccezione immediata
$user->campo_inesistente = 'valore'; // → \InvalidArgumentException("Unknown field: campo_inesistente")
```

Questo include `fill()`:

```php
// BEFORE (2.2.2) — ignorava i campi non nello schema
$user->fill(['name' => 'Test', 'nonexistent' => 'ignored']);

// AFTER (2.2.3) — eccezione
$user->fill(['name' => 'Test', 'nonexistent' => 'ignored']); // → \InvalidArgumentException
```

**Action**: Verifica tutti gli assegnamenti a oggetti `NanoORM` — i nomi dei campi devono corrispondere esattamente allo schema del database.

### 3. `paginate()` ora blocca query con JOIN

Se avevi registrato JOIN con `addJoin()` e chiamavi `paginate()`, i JOIN venivano ignorati silenziosamente. Ora `paginate()` lancia un'eccezione esplicita.

```php
// BEFORE (2.2.2) — JOIN ignorati, risultati incompleti
$orm->addJoin('users', 'user_id', 'id');
$result = $orm->paginate(1, 10); // JOIN ignorato, paginava solo orders

// AFTER (2.2.3) — eccezione
$orm->addJoin('users', 'user_id', 'id');
$result = $orm->paginate(1, 10); // → \Exception("Paginate does not support joined queries")
```

**Action**: Chiama `paginate()` PRIMA di `addJoin()`, oppure usa `fetchWithJoins()` per query con JOIN.

### 4. `sessionStart()` — interpretazione booleana corretta

Prima, `(int)(bool)"false"` produceva `1` perché in PHP `(bool)"false"` è `true`. Ora `filter_var("false", FILTER_VALIDATE_BOOLEAN)` produce `false` correttamente.

```env
# BEFORE (2.2.2) — questi valori venivano interpretati come true
SESSION.COOKIE_SECURE=false
SESSION.COOKIE_HTTPONLY=false

# AFTER (2.2.3) — ora vengono rispettati come false
SESSION.COOKIE_SECURE=false
SESSION.COOKIE_HTTPONLY=false
```

**Action**: Se usavi `SESSION.COOKIE_SECURE=false` e contavi sul fatto che venisse ignorato (comportamento buggato), ora la sessione partirà senza cookie sicuri. Se vuoi `true`, scrivi esplicitamente `true`.

### 5. `saveConfig()` ora lancia eccezione su fallimento rename

Prima, se `rename()` del file `.env` falliva (es. file bloccato da antivirus), la cache in memoria veniva aggiornata comunque ma il file non veniva salvato — nessun errore. Ora lancia eccezione.

```php
// BEFORE (2.2.2) — fallimento silenzioso
$app->configSet('DB.HOST', 'newhost'); // cache aggiornata, file forse no

// AFTER (2.2.3) — eccezione su fallimento
$app->configSet('DB.HOST', 'newhost'); // → \Exception("Failed to save config file: ...")
```

**Action**: Avvolgi `configSet()` in try/catch se operi in ambienti con possibili lock file.

### 6. `curlRequest()` ora blocca URL senza host

URL malformati come `http:///path` ora vengono respinti da `validateUrlNotRestricted()`.

```php
// BEFORE (2.2.2) — passava la validazione
NanoCore::curlRequest('http:///path'); // "funzionava" (curl falliva dopo)

// AFTER (2.2.3) — eccezione immediata
NanoCore::curlRequest('http:///path'); // → \Exception("URL must specify a host")
```

**Action**: Verifica che gli URL passati a `curlRequest()` abbiano sempre un host valido.

### 7. `declare(strict_types=1)` in entrambi i file

PHP ora applica type-checking strict. Parametri con tipo sbagliato lanciano `TypeError` invece di essere convertiti automaticamente.

```php
// BEFORE (2.2.2) — PHP convertiva automaticamente
$app->addRoute(123, $path, $handler); // 123 veniva castato a "123"

// AFTER (2.2.3) — TypeError
$app->addRoute(123, $path, $handler); // → TypeError: expected string, got int
```

**Action**: Verifica che tutti i parametri passati ai metodi pubblici corrispondano ai type hint dichiarati.

### 8. `renderHtml()` passa da `str_replace` a `strtr`

Il sistema di template ora usa `strtr()` invece di `str_replace()`, che matcha la chiave più lunga prima. Placeholder sovrapposti cambiano comportamento.

```php
// Se avevi placeholder sovrapposti:
$result = $app->renderHtml($template, ['{{NAME}}' => 'A', '{{NAME}_X}' => 'B']);

// BEFORE (2.2.2) — ordine imprevedibile con str_replace
// AFTER (2.2.3) — deterministico: match più lungo vince ({{NAME}_X} sostituito prima)
```

**Action**: Verifica che i placeholder nei template non abbiano prefissi sovrapposti. Se usi `{{nome}}` semplice non ci sono problemi.

---

## Nuove Protezioni

Queste non rompono nulla ma cambiano il comportamento a runtime:

| Change | Cosa succede |
|--------|-------------|
| `error_log()` per direttive PHP.INI sconosciute | Ogni direttiva `PHP.INI.*` non nella whitelist viene loggata come warning |
| `loadConfig()` eccezione su creazione `.env` | Se `.env` non esiste e non può essere creato (permessi), ora lancia eccezione |
| `rollbackDir()` validazione nomi migration | I nomi letti dal DB vengono validati con regex prima di costruire il path del file |
| `sendJsonError()` usa `$status` clampato nel JSON `code` | Il campo `code` nel JSON body ora corrisponde all'HTTP status (es. 500 invece di 0) |

---

## Migration Checklist

- [ ] Cerca `$json['message']` nel parsing errori — sostituisci con `$json['error']`
- [ ] Cerca assegnamenti a `NanoORM` con nomi di campo — verifica corrispondenza con lo schema DB
- [ ] Cerca `paginate()` dopo `addJoin()` — scambia l'ordine o usa `fetchWithJoins()`
- [ ] Cerca `SESSION.*=false` in `.env` — verifica che il valore booleano sia quello desiderato
- [ ] Cerca `configSet()` in contesti con possibili lock file — aggiungi try/catch
- [ ] Cerca URL malformati passati a `curlRequest()` — aggiungi host valido
- [ ] Cerca chiamate a metodi pubblici con tipo sbagliato — allinea ai type hint
- [ ] Cerca placeholder template sovrapposti — rendi le chiavi uniche
- [ ] Esegui la test suite per verificare la compatibilità
