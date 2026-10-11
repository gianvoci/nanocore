> Read this when you change template rendering or anything that writes `nanocore.log`.

# Filesystem: Templates & Log

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoCore.php` | `renderHtml()`, `logUnhandledException()`, `emit()`, `curlRequest()` log block, `execDetach()`, `$logBasePath` |

## Flow

**Templates**: `realpath($filename)` → must exist → must be inside an allowed root → must not be the `.env` file → read → escape string values (if `$escape`) → `strtr($tpl, $data)` (keys are literal placeholders, e.g. `{{NAME}}`).

**Log**: writers append `[timestamp] <context>: <message>` to `{dirname(.env)}/nanocore.log` with `FILE_APPEND | LOCK_EX`.

| Writer | Context |
| --- | --- |
| `logUnhandledException()` | `Unhandled exception (<class>)` — only when `CORE.EXPOSE_ERRORS=false` and status ≥ 500 |
| `emit()` | `Event listener error (<event>)` |
| `curlRequest()` | `curlRequest <METHOD> <url> -> <status> (<ms>ms) \| params: ... \| response: ...` (successes and HTTP errors); `curlRequest <METHOD> <url> -> failed after <n> attempts (cURL ...) \| params: ...` (transport failures) |
| `execDetach()` | Raw command output (POSIX: stdout only; Windows: stdout+stderr) |

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| Template path confinement | `realpath` + prefix match with trailing `DIRECTORY_SEPARATOR` against roots: `realpath(basePath ?: '.')` and the `.env` directory | `renderHtml()` |
| Config secrecy | `realpath($configFile)` rejected as template | `renderHtml()` |
| XSS | `htmlspecialchars(ENT_QUOTES, 'UTF-8')` on string values when `$escape = true` (default); non-strings inserted as-is | `renderHtml()` |
| Log line forging | CR/LF stripped in `logUnhandledException()` and in the `curlRequest()` log lines (incl. VT, FF, U+2028, U+2029 via `LOG_LINE_BREAKS`); `emit()` logs listener messages unstripped | `logUnhandledException()`, `curlRequest()`, `emit()` |
| Log never breaks error path | `@file_put_contents` + `error_log()` fallback | `logUnhandledException()`, `curlRequest()` log block |

Note: in HTTP mode the first root is the URL base path resolved as a filesystem path relative to cwd, not the document root.

## Config

| Item | Value |
| --- | --- |
| Log location | `.env` directory; `execDetach` falls back to the package root if no instance set it |
| Log rotation | none |
