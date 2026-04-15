# NanoCore

Lightweight PHP library providing routing, configuration management, a micro ORM, and utility functions. Consumed via Composer by other projects.

## Architecture

- **Language**: PHP >=8.0
- **Namespace**: `NanoCore` (PSR-4, maps to project root)
- **Entry points**: Consumers instantiate `NanoCore\NanoCore` and call `addRoute()` + `run()`
- **Config**: `app.json` at project root, dot-notation access (`CORE.ROOT`, `PHP.INI`, etc.)
- **No framework dependencies** — standalone, only requires PHP 8.0+

## Self-Maintenance Rule

> When modifying code, update the relevant file in `docs/`.

## Documentation Index

| File | Description |
| --- | --- |
| docs/file-structure.md | Directory map and purpose of each file |
| docs/commands.md | Dev, test, and lint commands |
| docs/conventions-backend.md | PHP coding conventions, patterns, and rules |
| docs/conventions-database.md | NanoORM usage, schema discovery, query patterns |
| docs/rules/error-handling.md | Exception and error handler patterns |
| docs/rules/route-registration.md | Route pattern syntax and dispatch behavior |
| docs/rules/config-management.md | Config get/set patterns and app.json structure |
| docs/rules/orm-usage.md | CRUD, hydration, joins, and magic property patterns |
