# Migration Guide: → 2.3.0

## Breaking Changes

### 1. `findBy()` / `findAll()` — nuova firma, ritorna `array<array>`

**Before**: `findBy(string $field, mixed $value, int $limit = 0): array` — ritornava array di istanze NanoORM. `findAll(array $conds, string $orderBy = '', int $limit = 0): array` — ritornava array di istanze NanoORM.

**After**: `findBy(string $where, array $params = [], ?int $limit = null): array` — ritorna `array<array>` (array associativi). `findAll(string $where = '', array $params = [], string $orderBy = '', ?int $limit = null): array` — ritorna `array<array>`. Passare `0` per "nessun limite" non funziona più — usa `null`.

Nessuna validazione del campo nella WHERE — il chiamante è responsabile della sicurezza SQL.

```php
// BEFORE — field/value, ritornava istanze NanoORM
$users = $orm->findBy('status', 'active', 10);
echo $users[0]->name;  // accesso oggetto

// AFTER — WHERE clause, ritorna array associativi
$users = $orm->findBy('status = ?', ['active'], 10);
echo $users[0]['name'];  // accesso array
```

**Action**: Sostituisci tutte le chiamate `findBy()` e `findAll()` con la nuova firma. Cambia l'accesso oggetto (`$row->field`) in accesso array (`$row['field']`).

### 2. `paginate()` — ritorna `array<array>` nel campo `data`

**Before**: `$result['data']` conteneva array di istanze NanoORM. Firma: `paginate(int $page, int $perPage, array $conds = [], string $orderBy = '')`.

**After**: `$result['data']` contiene array di array associativi. Firma: `paginate(int $page, int $perPage, string $where = '', array $params = [], string $orderBy = '')`.

```php
// BEFORE — istanze NanoORM in data
$result = $orm->paginate(1, 25, ['status' => 'active'], 'name ASC');
$result['data'][0]->name;  // accesso oggetto

// AFTER — array associativi in data
$result = $orm->paginate(1, 25, 'status = ?', ['active'], 'name ASC');
$result['data'][0]['name'];  // accesso array
```

**Action**: Cambia le chiamate `paginate()` alla nuova firma. Cambia l'accesso oggetto sugli elementi di `$result['data']` in accesso array.

### 3. `hydrate()` → `fromArray()` — ora pubblico con validazione campi

**Before**: `hydrate()` era private, usato internamente da `findById()`.

**After**: `fromArray(array $row): self` è pubblico. Valida i campi contro lo schema — i campi sconosciuti lanciano `\InvalidArgumentException("Unknown field: {$key}")`.

```php
// AFTER — metodo pubblico, valida i campi
$user = (new NanoORM($pdo, 'users'))->fromArray(['name' => 'Test', 'email' => 'a@b.c']);
// campi sconosciuti NON sono ammessi:
// (new NanoORM($pdo, 'users'))->fromArray(['unknown' => 'x']);  // → \InvalidArgumentException
```

**Action**: Usa `fromArray()` per idratazione manuale. Per dati JOIN con campi extra, usa `fetchWithJoins()` invece.

### 4. `deleteWhere()` — nuova firma con WHERE arbitraria

**Before**: `deleteWhere(array $conds): int` — array associativo di condizioni.

**After**: `deleteWhere(string $where, array $params = []): int` — clausola WHERE con params. WHERE vuoto lancia `\Exception` (guardia di sicurezza).

```php
// BEFORE — array associativo
$orm->deleteWhere(['status' => 'inactive']);

// AFTER — clausola WHERE
$orm->deleteWhere('status = ?', ['inactive']);
$orm->deleteWhere('');  // → \Exception (sicurezza: previene delete accidentale di tutto)
```

Per cancellare tutte le righe intenzionalmente: `deleteWhere('1=1')`.

**Action**: Sostituisci `deleteWhere(['field' => 'value'])` con `deleteWhere('field = ?', ['value'])`. Se devi cancellare tutte le righe, usa `deleteWhere('1=1')`.

### 5. Middleware riceve `$route` e `$method`

**Before**: La signature del callback middleware era `function ($app, $params, $next)`.

**After**: Il callback middleware riceve due parametri aggiuntivi: `function ($app, $params, $next, $route, $method)`.

```php
// BEFORE
$app->addMiddleware(function ($app, $params, $next) { ... });

// AFTER
$app->addMiddleware(function ($app, $params, $next, $route, $method) {
    // $route = '/api/auth/login'
    // $method = 'POST'
});
```

**Action**: I middleware esistenti con 3 parametri funzionano ancora (PHP permette argomenti extra). Aggiorna la signature se vuoi usare `$route`/`$method`.

### 6. `getBodyRequest()` — cache e auto-detect Content-Type

**Before**: Ogni chiamata rileggeva `php://input`. Solo JSON era supportato.

**After**: La prima chiamata legge e cache in `$storage['__nc_body']`. Auto-detect del Content-Type: JSON, form-urlencoded, multipart/form-data. `$maxBytes` ha effetto solo sulla prima chiamata. Nuovo parametro `$validateContentType` (secondo argomento, default `false`): quando `true`, richiede Content-Type `application/json` e lancia eccezione se non corrisponde. Content-Type sconosciuti: tentativo di decode JSON, poi fallback a stringa raw.

```php
// BEFORE — sempre JSON, nessuna cache
$body = $app->getBodyRequest();

// AFTER — cached, auto-detect Content-Type
$body = $app->getBodyRequest();  // prima chiamata: legge + cache
$body = $app->getBodyRequest();  // chiamate successive: ritorna cache
```

**Action**: Se facevi affidamento su chiamate multiple a `getBodyRequest()` con `$maxBytes` diversi, solo il limite della prima chiamata viene applicato. Se facevi affidamento sul rifiuto di body non-JSON, ora vengono auto-parsati. Se hai bisogno di imporre `application/json`, passa `true` come secondo argomento: `$app->getBodyRequest(null, true)`.

### 7. Nuovo metodo `require(string $key): mixed`

Accesso fail-fast alle proprietà. Controlla `$storage` poi le proprietà virtuali (`body`, `cli`). Lancia `RuntimeException` per chiavi sconosciute.

```php
$pdo = $app->require('pdo');
$body = $app->require('body');   // proprietà virtuale
$cli = $app->require('cli');     // proprietà virtuale
$app->require('nonexistent');     // → RuntimeException
```

**Action**: Usa `require()` invece del pattern `isset` + accesso per valori di config obbligatori.

### 8. `execDetach()` — fix Windows

**Before**: Su Windows, `execDetach()` poteva mostrare finestre popup o bloccare.

**After**: Usa `start /B cmd /c "..."` con redirect su log — nessun popup, non-blocking.

**Action**: Nessuna modifica al codice necessaria. Solo fix di comportamento.

---

## Nuove Protezioni

Queste non rompono nulla ma cambiano il comportamento a runtime:

| Change | Cosa succede |
|--------|-------------|
| `getBodyRequest()` cache | Previene doppia lettura di `php://input` |
| `require()` throw su chiave mancante | `RuntimeException` invece di null silenzioso |
| `deleteWhere('')` safety guard | Previene DELETE senza WHERE accidentale |

---

## Migration Checklist

- [ ] Cerca chiamate `findBy('field', 'value')` — sostituisci con `findBy('field = ?', ['value'])` e cambia accesso da oggetto ad array
- [ ] Cerca chiamate `findAll(['field' => 'value'])` — sostituisci con `findAll('field = ?', ['value'])` e cambia accesso da oggetto ad array
- [ ] Cerca chiamate `paginate()` con array di condizioni — sostituisci con nuova firma WHERE + params
- [ ] Cerca accesso oggetto su risultati `paginate()` (`$row->field`) — cambia in accesso array (`$row['field']`)
- [ ] Cerca usi di `hydrate()` — sostituisci con `fromArray()` (pubblico, lancia `\InvalidArgumentException` per campi sconosciuti)
- [ ] Cerca chiamate `deleteWhere(['field' => 'value'])` — sostituisci con `deleteWhere('field = ?', ['value'])`
- [ ] Verifica che `deleteWhere('')` non venga usato per cancellare tutto — usa `deleteWhere('1=1')` se intenzionale
- [ ] Verifica middleware esistenti — aggiungi `$route` e `$method` alla signature se servono
- [ ] Cerca chiamate multiple a `getBodyRequest()` con `$maxBytes` diversi — solo il primo ha effetto
- [ ] Verifica che il parsing di body non-JSON non causi problemi — ora vengono auto-parsati
- [ ] Sostituisci pattern `isset` + accesso con `require()` per valori di config obbligatori
- [ ] Cerca chiamate `findBy()`/`findAll()` che passano `0` o `false` come limite — sostituisci con `null` per nessun limite
- [ ] Esegui la test suite per verificare la compatibilità