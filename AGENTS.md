# NanoCore

Lightweight PHP library providing routing, configuration management, a micro ORM, and utility functions. Consumed via Composer by other projects.

## Architecture

- **Language**: PHP >=8.5
- **Namespace**: `NanoCore` (PSR-4, maps to `src/`)
- **Entry points**: Consumers instantiate `NanoCore\NanoCore` and call `addRoute()` + `run()`
- **Config**: `.env` at project root, dot-notation access (`CORE.ROOT`, `PHP.INI`, etc.)
- **No framework dependencies** — standalone, only requires PHP 8.5+

## Self-Maintenance Rules

> - When modifying code, update the relevant file in `specs/`.
> - When modifying or implementing a feature, add or update the corresponding tests in `tests/cases/`.
> - Before committing, bump the version from the latest git tag (`git tag --sort=-v:refname | head -1`) and create a `MIGRATION-to-{version}.md` file documenting breaking changes, new features, and migration steps. Update `CHANGELOG.md` with the new version entry. After commit, communicate to the user the exact git commands to tag and push: `git tag {version}` and `git push origin main --tags`.

## Documentation Index

| File | Description |
| --- | --- |
| specs/file-structure.md | Directory map and purpose of each file |
| specs/commands.md | Dev, test, and lint commands |
| specs/conventions-backend.md | PHP coding conventions, patterns, and rules (response methods, middleware, validation, events, CLI, sessions, security) |
| specs/conventions-database.md | NanoORM usage, schema discovery, query patterns, pagination, transactions, migrations |
| specs/conventions-testing.md | Test conventions, patterns, and rules |
| specs/rules/error-handling.md | Exception and error handler patterns, response dispatch, built-in events |
| specs/rules/route-registration.md | Route pattern syntax, dispatch flow, middleware, events |
| specs/rules/config-management.md | Config get/set patterns, .env structure, SESSION.* config keys |
| specs/rules/orm-usage.md | CRUD, hydration, joins, pagination, transactions, migrations |
