> Read this when you add or change a validation rule.

# Add a Validation Rule

Contract: [validation/spec.md](../validation/spec.md).

1. Add a `match` arm in `applyRule()` (`src/NanoCore.php`) returning `null` on pass or a message `"Field '{$field}' ..."` on failure:

```php
'alpha' => preg_match('/^\pL+$/u', (string)$value) === 1
    ? null
    : "Field '{$field}' must contain only letters",
```

2. Complex logic → a `private function validateXxx(string $field, mixed $value, string $param): ?string`, like `validateIn()`.
3. Rule needs a parameter? Validate it in `parseRule()` and throw `\InvalidArgumentException("Rule '{$rule}' requires ...")`.
4. Never change the pass/fail outcome or message of an existing rule outside a major release.
5. Add tests (pass, fail, absent optional field) in `tests/cases/NewFeaturesTest.php` or the relevant file.
6. Update the rule table in [validation/spec.md](../validation/spec.md) and `README.md` → Built-in Rules (rule count in Features).
