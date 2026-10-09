> Read this when you finish a change that will be committed and released.

# Release a Version

1. Find the latest version: `git tag --sort=-v:refname | head -1` (tags use the `v` prefix, e.g. `v2.4.1`); compare with the top entry of `CHANGELOG.md`.
2. Pick the bump (SemVer):

| Change | Bump |
| --- | --- |
| Changed JSON error shape, accepted validation input, public/protected signature, exception class/code consumers catch, protected keys | major |
| New method/config key/option, new rejection of clearly invalid input, security hardening with unchanged defaults | minor |
| Bug fix with no contract change, docs, tests | patch |

3. Create `MIGRATION-to-{version}.md` in English, following `MIGRATION-to-2.5.0.md`: intro line, **Behavior Changes** (each with **Action**), **New Features** (with `.env`/PHP snippet), **Migration Checklist**.
4. Add a `## [{version}] - YYYY-MM-DD - <type>(<scope>): <summary>` entry at the top of `CHANGELOG.md`, bullets with backticked identifiers, followed by `---`.
5. Update `README.md` sections for every public behavior change, and the affected `specs/` files.
6. `php -l` both sources and `php tests/runAllTests.php` must pass.
7. After the commit, give the user the commands (do not run them):

```bash
git tag v{version}
git push origin main --tags
```
