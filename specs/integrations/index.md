> Read this when you touch any code that crosses a process boundary (HTTP in/out, DB, filesystem, shell, session store).

# Integrations

| Name | Direction | Protocol | Implementing files | Doc |
| --- | --- | --- | --- | --- |
| Inbound request (HTTP + CLI argv) | IN | HTTP / `$_SERVER['argv']` | `src/NanoCore.php` (`run`, `getBodyRequest`, `sendJsonError`, `redirect`, `runCli`) | [inbound-request.md](inbound-request.md) |
| Outbound HTTP | OUT | HTTP(S) via curl_multi | `src/NanoCore.php` (`curlRequest`, `validateUrlNotRestricted`, `validateIpNotRestricted`, `mask*`) | [outbound-http.md](outbound-http.md) |
| Database | BIDIRECTIONAL | PDO (MySQL, SQLite) | `src/NanoORM.php` | [database.md](database.md) |
| `.env` config file | BIDIRECTIONAL | Filesystem | `src/NanoCore.php` (config manager, `setPHPConfig`) | [env-config.md](env-config.md) |
| Templates & log file | IN (templates) / OUT (log) | Filesystem | `src/NanoCore.php` (`renderHtml`, `nanocore.log` writers) | [filesystem.md](filesystem.md) |
| Sessions | BIDIRECTIONAL | PHP session + cookie | `src/NanoCore.php` (`session*`) | [sessions.md](sessions.md) |
| Background process | OUT | Shell (`shell_exec` / `popen`) | `src/NanoCore.php` (`execDetach`) | [process.md](process.md) |

Rule: guards listed in each file's **Trust boundaries** table must not be relaxed, bypassed, or made opt-in without a major release and a `MIGRATION-to-{version}.md` entry.
