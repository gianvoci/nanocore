> Read this when you change `execDetach()` or launch external processes.

# Background Process

## Implementing files

| File | Key symbols |
| --- | --- |
| `src/NanoCore.php` | `execDetach()` |

## Flow

1. Build the command line from `string|array $cmd` (escaping below).
2. Windows: `pclose(popen('start /B cmd /c "<cmd> >> <log> 2>&1"', 'r'))`.
3. POSIX: `shell_exec('<cmd> >> <log> 2>/dev/null &')`, then flush output buffers.
4. Returns immediately; no PID, exit code, or error is reported.

## Trust boundaries

| Boundary | Mechanism | Location |
| --- | --- | --- |
| Array form (preferred) | POSIX: program via `escapeshellcmd`, each arg via `escapeshellarg`; Windows: every element via `escapeshellarg` | `execDetach()` |
| String form (legacy) | Whole string via `escapeshellcmd` (does not prevent argument injection) | `execDetach()` |
| Log path | `escapeshellarg` on `nanocore.log` path | `execDetach()` |

Never pass user input in string form; use the array form.

## Config

| Item | Value |
| --- | --- |
| Output sink | `nanocore.log` in `.env` dir, fallback package root (`dirname(__DIR__)`) when no `NanoCore` was constructed |
