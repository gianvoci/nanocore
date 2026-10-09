> Read this when you change or rely on `validate()` / `check()` rule semantics.

# Validation

## Purpose

Rule-string validation of input arrays. Which inputs are accepted is a public contract: changing it in either direction is breaking.

## Data model

| Item | Shape |
| --- | --- |
| Rules | `['field' => 'rule1\|rule2:param', ...]` |
| Parsed rule | `['name' => string, 'param' => ?string]` — split on the first `:` |
| `check()` result | `['valid' => bool, 'errors' => ['field' => string[]], 'data' => ['field' => value]]` |

## Business rules

1. A field absent from `$data` is skipped entirely unless its rules contain `required`. A present key (even `null`) runs all its rules.
2. All rules of a field run; errors accumulate (no short-circuit after `required`).
3. `data` contains only fields listed in `$rules` that produced no error; extra input keys are dropped.
4. `validate()` throws `\Exception('Validation failed', 422)` with no details; use `check()` to get messages.
5. Unknown rule name → `\InvalidArgumentException('Unknown validation rule: <name>')` (surfaces as 500, not 422).
6. `min`/`max` without numeric param → `\InvalidArgumentException("Rule '<rule>' requires a numeric parameter")`.

| Rule | Passes when | Gotcha |
| --- | --- | --- |
| `required` | value not `null` and not `''` | `0`, `'0'`, `[]` pass |
| `integer` | `is_numeric` and `floor(v) == v` | `'1.0'`, `'1e3'` pass |
| `numeric` | `is_numeric` | |
| `string` | `is_string` | |
| `min:n` / `max:n` | numeric value (incl. numeric strings) → numeric compare; otherwise `mb_strlen` compare | `'123'` with `string\|min:8` passes (numeric compare). Kept for compatibility — changing it is a major-release change |
| `email` | `FILTER_VALIDATE_EMAIL` | |
| `url` | `FILTER_VALIDATE_URL` | Any scheme; not an SSRF check |
| `in:a,b` | strict `in_array` against comma-split strings | Integer `1` fails `in:1,2`; no spaces trimmed |
| `regex:p` | `preg_match('/' . p . '/', (string) v) === 1` | No delimiters/flags in param; escape `/`; `\|` cannot appear (rule separator); invalid pattern → `has invalid regex pattern` error (not 500) |

Error messages are fixed strings of the form `Field '<field>' ...` (see `applyRule()`); tests assert them verbatim.

## Constants

None; rule names are the `match` arms in `applyRule()` (`src/NanoCore.php`).

## Cross-links

- API: [api-routes.md](../api-routes.md#nanocore--validation)
- Add a rule: [rules/add-validation-rule.md](../rules/add-validation-rule.md)
