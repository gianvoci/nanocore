<?php

declare(strict_types=1);

/**
 * NanoCore - A small, lite, mini PHP framework
 *
 * @author Giancarlo Voci
 * @since 2024-05-11
 */

namespace NanoCore;

use ErrorException;

class NanoCore
{
    private const PROTECTED_CONFIG_KEYS = ['PHP.INI', 'CORE'];

    private const ALLOWED_INI_SETTINGS = [
        'display_errors',
        'error_log',
        'error_reporting',
        'log_errors',
        'upload_max_filesize',
        'post_max_size',
        'max_execution_time',
        'memory_limit',
        'default_charset',
        'date.timezone',
        'session.cookie_httponly',
        'session.cookie_secure',
        'session.use_strict_mode',
    ];

    private array $routes = [];
    private string $basePath;
    private ?string $configFile;

    private ?array $configCache = null;
    private static ?string $logBasePath = null;
    private array $storage = [];
    private array $middlewares = [];
    private array $listeners = [];
    private array $commands = [];

    public function __construct(string $configFile = '.env')
    {
        $this->setErrorHandlers();
        $this->configFile = $this->validateConfigPath($configFile);

        $this->basePath = $this->getBasePath();
        self::$logBasePath = dirname($this->configFile);
        $this->setPHPConfig();

        // Set CORE.ROOT directly in cache — bypasses protected-key check
        $this->loadConfig();
        $this->configCache['CORE']['ROOT'] = $this->basePath;
    }

    private function setPHPConfig(): void
    {
        $iniSettings = $this->configGet('PHP.INI') ?? [];
        foreach ($iniSettings as $setting => $value) {
            if (!in_array($setting, self::ALLOWED_INI_SETTINGS, true)) {
                error_log("NanoCore: unknown PHP.INI setting '{$setting}' will be ignored");
                continue;
            }
            ini_set($setting, $value);
        }
    }

    /**
     * Validate and resolve the config file path within the project directory.
     */
    private function validateConfigPath(string $configFile): string
    {
        $dir = dirname($configFile);
        $resolved = realpath($dir);

        if ($resolved === false) {
            $resolved = realpath('.');
        }

        $configFile = $resolved . DIRECTORY_SEPARATOR . basename($configFile);

        // Only enforce .env naming for new files — existing files are accepted as-is
        if (!file_exists($configFile) && !str_starts_with(basename($configFile), '.env')) {
            throw new \Exception("Config file must start with .env, got: " . basename($configFile));
        }

        return $configFile;
    }

    private function setErrorHandlers(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        set_exception_handler(function ($exception): void {
            $this->sendJsonError($exception);
        });
    }

    /**
     * Send a JSON error response and terminate.
     */
    private function sendJsonError(\Throwable $exception): void
    {
        $status = (int)$exception->getCode();
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode(
            [
                'error' => $exception->getMessage(),
                'code'  => $status,
            ],
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * A method to get the base path depending on the PHP server API.
     *
     * @return string The base path determined based on the server API.
     */
    private function getBasePath(): string
    {
        if (php_sapi_name() === 'cli') {
            return '';
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return ($basePath === '' || $basePath === '.') ? '' : $basePath;
    }

    /**
     * Normalize route paths for consistent route registration and lookup.
     */
    private function normalizeRoutePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#u', '/', $path);
        $path = '/' . ltrim((string)$path, '/');
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Strip configured base path from a normalized route path.
     */
    private function removeBasePathPrefix(string $path): string
    {
        if ($this->basePath === '') {
            return $path;
        }

        if (str_starts_with($path, $this->basePath)) {
            $path = mb_substr($path, mb_strlen($this->basePath));
            $path = $path === false ? '' : $path;
        }

        return $this->normalizeRoutePath($path);
    }

    /**
     * Convert route definitions to a regex and return captured path parameter names.
     */
    private function buildRoutePattern(string $path, array &$paramNames): string
    {
        $paramNames = [];

        if ($path === '/') {
            return '#^/$#';
        }

        $segments = explode('/', ltrim($path, '/'));
        $parts = [];

        foreach ($segments as $segment) {
            if ($segment === '@*') {
                $paramNames[] = 'wildcard';
                $parts[] = '(?P<wildcard>.*)';
                break;
            }

            if (str_starts_with($segment, '@')) {
                $name = preg_replace('/[^a-zA-Z0-9_]/', '', substr($segment, 1));
                $name = $name === '' ? 'param' . count($paramNames) : $name;
                $paramNames[] = $name;
                $parts[] = '(?P<' . $name . '>[^/]+)';
                continue;
            }

            $parts[] = preg_quote($segment, '#');
        }

        return '#^/' . implode('/', $parts) . '$#';
    }

    /**
     * A method to add a route to the routes array after adjusting the path.
     *
     * @param mixed $method The HTTP method of the route.
     * @param string $path The path of the route.
     * @param mixed $handler The handler for the route.
     */
    public function addRoute(string $method, string $path, callable $handler): void
    {
        $method = strtoupper((string)$method);
        $path = $this->removeBasePathPrefix($this->normalizeRoutePath((string)$path));

        $paramNames = [];
        $pattern = $this->buildRoutePattern($path, $paramNames);

        $this->routes[$method][] = [
            'handler' => $handler,
            'pattern' => $pattern,
            'params' => $paramNames,
        ];
    }

    /**
     * A method to run the application logic based on the request method and route.
     *
     * @throws \Exception When an error occurs during the application execution.
     * @return mixed The response from the handler.
     */
    public function run(): mixed
    {
        try {
            if (php_sapi_name() === 'cli' && !empty($this->commands)) {
                $this->runCli();
                return null;
            }

            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $rawUri = $_SERVER['REQUEST_URI'] ?? ($_SERVER['argv'][1] ?? '/');
            $params = php_sapi_name() === 'cli' ? array_slice($_SERVER['argv'], 2) : [];

            $parsedPath = parse_url($rawUri, PHP_URL_PATH) ?? '/';
            $queryString = parse_url($rawUri, PHP_URL_QUERY) ?? '';
            if ($queryString !== '') {
                $queryParams = [];
                parse_str($queryString, $queryParams);
                $params = array_merge($params, $queryParams);
            }

            $uri = $this->removeBasePathPrefix($this->normalizeRoutePath((string)$parsedPath));

            if (isset($this->routes[$method])) {
                foreach ($this->routes[$method] as $route) {
                    if (!preg_match($route['pattern'], $uri, $matches)) {
                        continue;
                    }

                    $pathParams = [];
                    foreach ($route['params'] as $name) {
                        if (isset($matches[$name])) {
                            $pathParams[$name] = $matches[$name];
                        }
                    }

                    $finalParams = array_merge($params, $pathParams);

                    $this->emit('route.matched', ['method' => $method, 'path' => $uri, 'params' => $finalParams]);

                    // Build middleware chain
                    $routeHandler = $route['handler'];
                    $chain = function (NanoCore $app, array $params) use ($routeHandler): mixed {
                        return $routeHandler($app, $params);
                    };

                    // Wrap middlewares in reverse order (last registered = outermost)
                    foreach (array_reverse($this->middlewares) as $middleware) {
                        $next = $chain;
                        $chain = function (NanoCore $app, array $params) use ($middleware, $next): mixed {
                            return $middleware($app, $params, $next);
                        };
                    }

                    $result = $chain($this, $finalParams);

                    if (is_array($result) && !empty($result['__nc_response'])) {
                        $this->sendResponse($result);
                        return null;
                    }
                    return $result;
                }
            }

            $this->emit('route.not_found', ['method' => $method, 'path' => $uri]);
            throw new \Exception('Route not found', 404);
        } catch (\Throwable $exception) {
            $this->emit('error', ['exception' => $exception]);
            $this->sendJsonError($exception);
            return null;
        }
    }
    #################
    # RESPONSE METHODS
    #################

    /**
     * Return a JSON response descriptor.
     */
    public function json(mixed $data, int $status = 200, array $headers = []): array
    {
        $customHeaders = [];
        foreach ($headers as $h) {
            if (!str_starts_with(strtolower($h), 'content-type:')) {
                $customHeaders[] = $h;
            }
        }
        return [
            '__nc_response' => true,
            'type'          => 'json',
            'body'          => $data,
            'status'        => $status,
            'headers'       => array_merge(['Content-Type: application/json'], $customHeaders),
        ];
    }

    /**
     * Return an HTML response descriptor.
     */
    public function html(string $content, int $status = 200, array $headers = []): array
    {
        return [
            '__nc_response' => true,
            'type'          => 'html',
            'body'          => $content,
            'status'        => $status,
            'headers'       => array_merge(['Content-Type: text/html; charset=UTF-8'], $headers),
        ];
    }

    /**
     * Return a redirect response descriptor.
     */
    public function redirect(string $url, int $status = 302): array
    {
        // Strip CR/LF to prevent CRLF header injection
        $url = str_replace(["\r", "\n"], '', $url);

        return [
            '__nc_response' => true,
            'type'          => 'redirect',
            'body'          => null,
            'status'        => $status,
            'headers'       => ["Location: {$url}"],
            'url'           => $url,
        ];
    }

    /**
     * Send an HTTP response from a response descriptor array.
     */
    private function sendResponse(array $descriptor): void
    {
        $status = $descriptor['status'];
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        http_response_code($status);

        foreach ($descriptor['headers'] as $header) {
            header($header);
        }

        $type = $descriptor['type'];

        // No body for redirects and no-content statuses
        if ($type === 'redirect' || $status === 204 || $status === 304) {
            $eventData = ['type' => $type, 'status' => $status];
            if ($type === 'redirect') {
                $eventData['url'] = $descriptor['url'] ?? null;
            }
            $this->emit('response.sent', $eventData);
            return;
        }

        if ($type === 'json') {
            echo json_encode($descriptor['body'], JSON_THROW_ON_ERROR);
            $this->emit('response.sent', ['type' => $type, 'status' => $status]);
            return;
        }

        if ($type === 'html') {
            echo $descriptor['body'];
        }

        $this->emit('response.sent', ['type' => $type, 'status' => $status]);
    }

    #############
    # MIDDLEWARE
    #############

    /**
     * Append a middleware to the chain.
     * Middlewares run in registration order (first added = first executed).
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    ################
    # VALIDATION
    ################

    /**
     * Validate data against rules, returning only validated fields.
     * Throws on failure — use check() if you need error details.
     *
     * @param array $data  Input data to validate.
     * @param array $rules Associative array of field => ruleString (e.g. 'required|integer|min:0').
     * @return array Validated data (only fields that passed all rules).
     * @throws \Exception When validation fails (code 422).
     */
    public function validate(array $data, array $rules): array
    {
        $result = $this->check($data, $rules);

        if (!$result['valid']) {
            throw new \Exception('Validation failed', 422);
        }

        return $result['data'];
    }

    /**
     * Check data against rules and return a detailed result.
     *
     * @param array $data  Input data to validate.
     * @param array $rules Associative array of field => ruleString.
     * @return array ['valid' => bool, 'errors' => array, 'data' => array]
     */
    public function check(array $data, array $rules): array
    {
        $errors    = [];
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);

            // Skip absent optional fields — only 'required' makes a field mandatory
            if (!array_key_exists($field, $data) && !in_array('required', $ruleList, true)) {
                continue;
            }

            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $error = $this->applyRule($field, $value, $rule);
                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => $validated,
        ];
    }

    /**
     * Apply a single validation rule to a field value.
     *
     * @param string $field Field name (for error messages).
     * @param mixed  $value The value to validate.
     * @param string $rule  Rule string (e.g. 'required', 'min:5').
     * @return string|null Error message if validation fails, null if it passes.
     * @throws \InvalidArgumentException On unknown rule or missing parameter.
     */
    private function applyRule(string $field, mixed $value, string $rule): ?string
    {
        $parsed = $this->parseRule($rule);

        return match ($parsed['name']) {
            'required' => ($value === null || $value === '')
                ? "Field '{$field}' is required"
                : null,

            'integer' => (!is_numeric($value) || floor((float)$value) !== (float)$value)
                ? "Field '{$field}' must be an integer"
                : null,

            'numeric' => !is_numeric($value)
                ? "Field '{$field}' must be numeric"
                : null,

            'string' => !is_string($value)
                ? "Field '{$field}' must be a string"
                : null,

            'min' => $this->validateMin($field, $value, $parsed['param']),

            'max' => $this->validateMax($field, $value, $parsed['param']),

            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? "Field '{$field}' must be a valid email"
                : null,

            'regex' => (function () use ($field, $value, $parsed): ?string {
                // regex:pattern — matches value against the given regex pattern.
                // The pattern is automatically wrapped in / delimiters.
                // Do NOT include delimiters in the param. Use regex:^/api/ instead of regex:^\/api\/.
                // Patterns containing / must escape it: regex:^\/api\/v1
                $pattern = '/' . $parsed['param'] . '/';
                $match = @preg_match($pattern, (string)$value);
                if ($match === false) {
                    return "Field '{$field}' has invalid regex pattern";
                }
                if ($match === 0) {
                    return "Field '{$field}' does not match the required pattern";
                }
                return null;
            })(),

            'in' => $this->validateIn($field, $value, $parsed['param']),

            'url' => filter_var($value, FILTER_VALIDATE_URL) === false
                ? "Field '{$field}' must be a valid URL"
                : null,

            default => throw new \InvalidArgumentException("Unknown validation rule: {$parsed['name']}"),
        };
    }

    /**
     * Validate the 'min' rule — numeric comparison or string length.
     */
    private function validateMin(string $field, mixed $value, string $param): ?string
    {
        if (is_numeric($value)) {
            if ((float)$value < (float)$param) {
                return "Field '{$field}' must be at least {$param}";
            }
            return null;
        }

        if (mb_strlen((string)$value) < (int)$param) {
            return "Field '{$field}' must be at least {$param} characters";
        }

        return null;
    }

    /**
     * Validate the 'max' rule — numeric comparison or string length.
     */
    private function validateMax(string $field, mixed $value, string $param): ?string
    {
        if (is_numeric($value)) {
            if ((float)$value > (float)$param) {
                return "Field '{$field}' must be at most {$param}";
            }
            return null;
        }

        if (mb_strlen((string)$value) > (int)$param) {
            return "Field '{$field}' must be at most {$param} characters";
        }

        return null;
    }

    /**
     * Validate the 'in' rule — value must be in a comma-separated allow-list.
     */
    private function validateIn(string $field, mixed $value, string $param): ?string
    {
        $allowed = explode(',', $param);

        if (!in_array($value, $allowed, true)) {
            return "Field '{$field}' must be one of: {$param}";
        }

        return null;
    }

    /**
     * Parse a rule string into name and optional parameter.
     *
     * @param string $rule Rule string (e.g. 'min:5', 'required').
     * @return array ['name' => string, 'param' => string|null]
     * @throws \InvalidArgumentException When min/max rules lack a numeric parameter.
     */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            $colonPos = strpos($rule, ':');
            $name     = mb_substr($rule, 0, $colonPos);
            $param    = mb_substr($rule, $colonPos + 1);
        } else {
            $name  = $rule;
            $param = null;
        }

        // min/max require a numeric parameter
        if (($name === 'min' || $name === 'max') && ($param === null || !is_numeric($param))) {
            throw new \InvalidArgumentException("Rule '{$rule}' requires a numeric parameter");
        }

        return [
            'name'  => $name,
            'param' => $param,
        ];
    }

    ###########
    # EVENTS
    ###########

    /**
     * Register a listener for an event.
     */
    public function on(string $event, callable $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;
    }

    /**
     * Emit an event, calling all registered listeners.
     * One broken listener does not break the chain — errors are logged.
     */
    public function emit(string $event, array $data = []): void
    {
        if (!isset($this->listeners[$event]) || empty($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            try {
                $listener($this, $data);
            } catch (\Throwable $e) {
                if (self::$logBasePath !== null) {
                    $logPath = self::$logBasePath . '/nanocore.log';
                    $logLine = sprintf(
                        '[%s] Event listener error (%s): %s',
                        date('Y-m-d H:i:s'),
                        $event,
                        $e->getMessage()
                    );
                    file_put_contents($logPath, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            }
        }
    }

    ################
    # CLI COMMANDS
    ################

    /**
     * Register a CLI command with a name and handler.
     */
    public function addCommand(string $name, callable $handler): void
    {
        if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $name)) {
            throw new \InvalidArgumentException("Invalid command name: {$name}");
        }

        $this->commands[$name] = $handler;
    }

    /**
     * Dispatch a CLI command from $_SERVER['argv'].
     */
    public function runCli(): void
    {
        $argc = $_SERVER['argc'] ?? 0;

        if ($argc < 2 || !isset($_SERVER['argv'][1])) {
            echo "Available commands:\n";
            foreach (array_keys($this->commands) as $name) {
                echo "{$name}\n";
            }
            exit(1);
        }

        $commandName = $_SERVER['argv'][1];

        if (!isset($this->commands[$commandName])) {
            echo "Unknown command: {$commandName}\nAvailable commands:\n";
            foreach (array_keys($this->commands) as $name) {
                echo "{$name}\n";
            }
            exit(1);
        }

        $args = array_slice($_SERVER['argv'], 2);

        $this->commands[$commandName]($this, $args);
    }

    ############
    # SESSIONS
    ############

    /**
     * Start a PHP session with config-driven cookie params.
     * Idempotent — safe to call multiple times.
     */
    public function sessionStart(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $httpOnly = $this->configGet('SESSION.COOKIE_HTTPONLY');
        if ($httpOnly !== null) {
            ini_set('session.cookie_httponly', (int)filter_var($httpOnly, FILTER_VALIDATE_BOOLEAN));
        }

        $secure = $this->configGet('SESSION.COOKIE_SECURE');
        if ($secure !== null) {
            ini_set('session.cookie_secure', (int)filter_var($secure, FILTER_VALIDATE_BOOLEAN));
        }

        $strict = $this->configGet('SESSION.USE_STRICT_MODE');
        if ($strict !== null) {
            ini_set('session.use_strict_mode', (int)filter_var($strict, FILTER_VALIDATE_BOOLEAN));
        }

        session_start();
    }

    /**
     * Get a value from the current session.
     */
    public function sessionGet(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a value in the current session.
     */
    public function sessionSet(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Destroy the current session and clear session data.
     */
    public function sessionDestroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_destroy();
        $_SESSION = [];
    }

    ################
    # CONFIG MANAGER
    ################

    /**
     * Load config from the .env file.
     * Returns the config array and caches it in memory.
     */
    private function loadConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        if (!file_exists($this->configFile)) {
            $written = @file_put_contents($this->configFile, '');
            if ($written === false) {
                throw new \Exception("Cannot create config file: {$this->configFile}");
            }
        }

        $config = [];
        $this->parseEnvFile($this->configFile, $config);

        $this->configCache = $config;
        return $this->configCache;
    }

    /**
     * Parse a .env file and populate/override config values.
     * Each line: KEY=value, with dot-notation for nested keys.
     * Supports comments, quoted values, inline comments, export prefix, and ${VAR} interpolation.
     */
    private function parseEnvFile(string $path, array &$config): void
    {
        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return;
        }

        $lines = explode("\n", $contents);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Strip 'export ' prefix
            if (str_starts_with($line, 'export ')) {
                $line = mb_substr($line, 7);
            }

            // Split on first '=' only
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Strip inline # comments, respecting quoted strings
            $value = $this->stripInlineComment($value);

            // Track whether the value was single-quoted (no interpolation)
            $isSingleQuoted = str_starts_with($value, "'") && str_ends_with($value, "'");

            // Strip surrounding quotes
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            } elseif ($isSingleQuoted) {
                $value = substr($value, 1, -1);
            }

            // Variable interpolation: replace ${VAR} with already-resolved values
            // Single-quoted values are literal — skip interpolation
            if (!$isSingleQuoted) {
                $value = preg_replace_callback(
                    '/\$\{([^}]+)\}/u',
                    function (array $matches) use ($config): string {
                        $resolved = $this->resolveDotKey($config, $matches[1]);
                        return $resolved ?? $matches[0];
                    },
                    $value
                );
            }

            // Rebuild nested array from dot-notation key
            $this->setNestedValue($config, $key, $value);
        }
    }

    /**
     * Strip inline comments from a value, respecting quoted strings.
     */
    private function stripInlineComment(string $value): string
    {
        // If the value starts with a quote, find the LAST matching quote
        // (not the first — values may contain internal quotes)
        if (str_starts_with($value, '"')) {
            $end = strrpos($value, '"');
            if ($end !== false && $end > 0) {
                return mb_substr($value, 0, $end + 1);
            }
            return $value;
        }
        if (str_starts_with($value, "'")) {
            $end = strrpos($value, "'");
            if ($end !== false && $end > 0) {
                return mb_substr($value, 0, $end + 1);
            }
            return $value;
        }

        // Unquoted: split on ' #' and take the first part
        $commentPos = strpos($value, ' #');
        if ($commentPos !== false) {
            $value = mb_substr($value, 0, $commentPos);
        }

        return trim($value);
    }

    /**
     * Resolve a dot-notation key against a nested array.
     */
    private function resolveDotKey(array $config, string $key): ?string
    {
        $parts = explode('.', $key);
        $current = $config;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return is_string($current) ? $current : null;
    }

    /**
     * Set a value at a dot-notation key path, creating nested arrays as needed.
     */
    private function setNestedValue(array &$config, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $current = &$config;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    /**
     * Atomically save configuration data to .env file.
     * Flattens nested arrays to dot-notation keys.
     */
    private function saveConfig(array $data): void
    {
        $flat = $this->flattenConfigArray($data);
        ksort($flat);

        $lines = [];
        foreach ($flat as $key => $value) {
            // Skip non-string leaf values (arrays that can't be represented in .env)
            if (is_array($value)) {
                continue;
            }

            $value = (string)$value;

            // Quote values containing special characters (#, spaces, ${, quotes, backslash)
            // so they survive round-trip through parseEnvFile
            if (preg_match('/[\s#"\'\\\\]/u', $value) || $value === '') {
                $value = '"' . str_replace('"', '\\"', $value) . '"';
            }

            $lines[] = $key . '=' . $value;
        }

        $content = implode("\n", $lines) . "\n";

        $tmpFile = tempnam(dirname($this->configFile), 'nc_cfg_');
        if ($tmpFile === false) {
            throw new \Exception("Failed to create temporary config file");
        }

        $written = file_put_contents($tmpFile, $content);
        if ($written !== false) {
            $renamed = rename($tmpFile, $this->configFile);
            if ($renamed === false) {
                @unlink($tmpFile);
                throw new \Exception("Failed to save config file: {$this->configFile}");
            }
            $this->configCache = $data;
        } else {
            @unlink($tmpFile);
        }
    }

    /**
     * Flatten a nested config array to dot-notation key-value pairs.
     */
    private function flattenConfigArray(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix . $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenConfigArray($value, $fullKey . '.'));
            } else {
                $result[$fullKey] = $value ?? '';
            }
        }
        return $result;
    }

    /**
     * Retrieves a configuration value for a specified key.
     *
     * @param string $key The key to retrieve the value for.
     * @return mixed The value associated with the key.
     */
    public function configGet(string $name): mixed
    {
        $data = $this->loadConfig();

        $path = explode('.', $name);
        foreach ($path as $prop) {
            $data = $data[$prop] ?? null;
        }
        return $data ?? null;
    }

    /**
     * Sets a configuration value for a specified key.
     *
     * @param string $prop The key to set the value for.
     * @param mixed $value The value to set for the key.
     * @throws \Exception When trying to modify a protected key or saving fails.
     */
    public function configSet(string $prop, mixed $value): void
    {
        $topLevelKey = explode('.', $prop)[0];
        if (in_array($topLevelKey, self::PROTECTED_CONFIG_KEYS, true)) {
            throw new \Exception("Cannot modify protected config key: {$topLevelKey}");
        }

        $config = $this->loadConfig();

        $path = explode('.', $prop);
        $data = &$config;
        foreach ($path as $segment) {
            if (!isset($data[$segment])) {
                $data[$segment] = [];
            }
            $data = &$data[$segment];
        }

        $data = $value;

        $this->saveConfig($config);
    }

    /**
     * Validate that a URL does not resolve to a restricted IP address.
     *
     * Known limitation: DNS rebinding attacks can bypass this check because
     * DNS resolution happens twice (once here via gethostbynamel, once by curl).
     * Between the two resolutions, DNS could return a different IP.
     * For critical security, consider using CURLOPT_RESOLVE to pin the validated IP.
     */
    public static function validateUrlNotRestricted(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['scheme'])) {
            throw new \Exception("Invalid URL");
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \Exception("URL scheme not allowed: {$scheme}");
        }

        $host = $parsed['host'] ?? '';

        if ($host === '') {
            throw new \Exception("URL must specify a host");
        }

        // Strip IPv6 brackets — parse_url returns [::1] with brackets
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = mb_substr($host, 1, -1);
        }

        // Block well-known internal hostnames
        $blockedHosts = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];
        if (in_array(strtolower($host), $blockedHosts, true)) {
            throw new \Exception("URL points to a restricted network address");
        }

        // If the host is a direct IP, validate it
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip) {
            self::validateIpNotRestricted($ip);
            return;
        }

        // For hostnames, resolve DNS and check the resulting IPs
        $resolvedIps = gethostbynamel($host);
        if ($resolvedIps === false || empty($resolvedIps)) {
            // Hostname doesn't resolve — let curl handle the error
            return;
        }

        foreach ($resolvedIps as $resolvedIp) {
            self::validateIpNotRestricted($resolvedIp);
        }
    }

    /**
     * Validate that an IP address is not in a private or restricted range.
     */
    public static function validateIpNotRestricted(string $ip): void
    {
        $flags = [FILTER_FLAG_NO_PRIV_RANGE, FILTER_FLAG_NO_RES_RANGE];
        foreach ($flags as $flag) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
                throw new \Exception("URL points to a restricted network address");
            }
        }
    }

    /**
     * A function to make a cURL request to a specified URL with optional parameters and headers.
     *
     * @param string $url The URL to make the request to.
     * @param array $options An optional array of options to customize the request.
     *                       Logical keys (string):
     *                       - 'method': HTTP method. Defaults to 'GET'.
     *                       - 'params': Request parameters. Defaults to [].
     *                       - 'headers': HTTP headers. Defaults to [].
     *                       - 'raw': bool, skip JSON decoding. Defaults to false.
     *                       - 'with_info': bool, return ['body'=>mixed,'status'=>int,'content_type'=>string|null] instead of just the body. Defaults to false.
     *                       CURLOPT keys (int):
     *                       Any CURLOPT_* constant can be passed to override the default curl settings.
     *                       Examples: CURLOPT_TIMEOUT, CURLOPT_CONNECTTIMEOUT, CURLOPT_WRITEFUNCTION.
     *                       These are merged directly into the curl options array.
     * @throws \Exception When an error occurs during the cURL request.
     * @return mixed The response from the cURL request, decoded as JSON if possible.
     */
    public static function curlRequest(string $url, array $options = []): mixed
    {
        self::validateUrlNotRestricted($url);

        $startTime = hrtime(true);

        $curlopt = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_MAXREDIRS      => 5,
        ];

        // Extract logical keys with defaults
        $method    = $options['method']    ?? 'GET';
        $params    = $options['params']    ?? [];
        $headers   = $options['headers']   ?? [];
        $raw       = $options['raw']       ?? false;
        $withInfo  = $options['with_info'] ?? false;

        // Remove logical keys — whatever remains are CURLOPT_* constants
        unset($options['method'], $options['params'], $options['headers'], $options['raw'], $options['with_info']);

        // Merge caller-provided CURLOPT_* overrides into defaults
        $curlopt = array_replace($curlopt, $options);

        // Force disable follow location for SSRF protection — redirect targets are not re-validated
        $curlopt[CURLOPT_FOLLOWLOCATION] = false;

        // Configure HTTP method
        $method = strtoupper($method);
        $curlopt[CURLOPT_CUSTOMREQUEST] = $method;

        if (!empty($params)) {
            if ($method === 'GET') {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            } else {
                $curlopt[CURLOPT_POSTFIELDS] = $params;
            }
        }

        // Add headers if provided
        if (!empty($headers)) {
            $curlopt[CURLOPT_HTTPHEADER] = $headers;
        }

        $curlopt[CURLOPT_URL] = $url;

        $ch = curl_init($url);

        curl_setopt_array($ch, $curlopt);

        $response = false;
        for ($retry = 0; $retry < 5; $retry++) {
            if ($retry > 0) {
                usleep(100000 * $retry);
                $ch = curl_init();
                curl_setopt_array($ch, $curlopt);
            }
            $response = curl_exec($ch);
            if ($response !== false) {
                break;
            }
        }

        if ($response === false) {
            throw new \Exception("External request failed");
        }

        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if ($contentType === false) {
            $contentType = null;
        }

        // Log the request
        if (self::$logBasePath !== null) {
            $duration = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $paramsStr = is_array($params) ? json_encode($params, JSON_UNESCAPED_SLASHES) : (string) $params;

            // Strip userinfo from URL before logging to avoid credential disclosure
            $safeUrl = preg_replace('#^(https?://)[^@]+@#', '$1', $url);

            if (isset($curlopt[CURLOPT_WRITEFUNCTION])) {
                $logBody = '[streamed]';
            } else {
                $logBody = mb_substr((string) $response, 0, 500, 'UTF-8') . (mb_strlen((string) $response) > 500 ? '... [truncated]' : '');
            }

            $logLine = sprintf(
                '[%s] curlRequest %s %s -> %d (%dms) | params: %s | response: %s',
                date('Y-m-d H:i:s'),
                $method,
                $safeUrl,
                $httpCode,
                $duration,
                $paramsStr,
                $logBody
            );
            $logPath = self::$logBasePath . '/nanocore.log';
            file_put_contents($logPath, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        // Determine the response body value
        if (isset($curlopt[CURLOPT_WRITEFUNCTION])) {
            // When CURLOPT_WRITEFUNCTION is set, curl_exec returns true on success — body consumed by callback
            $body = $response;
        } elseif ($raw) {
            // When 'raw' option is true, skip JSON decoding
            $body = $response;
        } else {
            // Decode JSON when valid, otherwise return raw response
            $decoded = json_decode($response, true);
            $body = json_last_error() === JSON_ERROR_NONE ? $decoded : $response;
        }

        // Return info array when with_info is requested
        if ($withInfo) {
            return [
                'body'         => $body,
                'status'       => (int) $httpCode,
                'content_type' => $contentType,
            ];
        }

        return $body;
    }

    /**
     * Retrieves the body content from the input stream and decodes it as JSON if possible.
     *
     * @param int $maxBytes Maximum bytes to read from the request body.
     * @param bool $validateContentType Whether to enforce application/json Content-Type.
     * @return mixed The decoded JSON content or the raw content if decoding fails.
     */
    public function getBodyRequest(int $maxBytes = 10_485_760, bool $validateContentType = false): mixed
    {
        if ($validateContentType) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            if ($contentType !== '' && !str_contains(strtolower($contentType), 'application/json')) {
                throw new \Exception("Content-Type must be application/json, got: {$contentType}");
            }
        }

        $content = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if (strlen($content) > $maxBytes) {
            throw new \Exception("Request body exceeds maximum size of {$maxBytes} bytes");
        }
        $decoded = json_decode($content, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
    }

    /**
     * Renders HTML content by replacing placeholders with provided data.
     *
     * @param string $filename The path to the HTML template file.
     * @param array $data An associative array containing data to replace in the template.
     * @param bool $escape Whether to HTML-escape string values in $data. Defaults to true.
     * @return string The rendered HTML content.
     */
    public function renderHtml(string $filename, array $data = [], bool $escape = true): string
    {
        // Resolve and validate the path
        $realPath = realpath($filename);

        if ($realPath === false || !file_exists($realPath)) {
            throw new \Exception("Template file not found: {$filename}");
        }

        // Ensure the template is within the project root
        // CLI mode: basePath is empty, use current working directory as root
        $rootPath = realpath($this->basePath ?: '.');
        if ($rootPath === false) {
            throw new \Exception("Template file path is outside the allowed directory");
        }
        // Append separator to prevent sibling directory bypass (e.g. /var/www2 matching /var/www)
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($realPath !== $rootPath && !str_starts_with($realPath, $rootPath)) {
            throw new \Exception("Template file path is outside the allowed directory");
        }

        $tpl = file_get_contents($realPath);

        if ($escape) {
            $escapedData = array_map(function ($value) {
                return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
            }, $data);
        } else {
            $escapedData = $data;
        }

        return strtr($tpl, $escapedData);
    }

    /**
     * Retrieves the specified property of nanocore.
     *
     * @param string $name The name of the property to retrieve.
     * @return mixed The value of the property or the result of the method execution.
     */
    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'body':
                return $this->getBodyRequest();
            case 'cli':
                return php_sapi_name() === 'cli';
            default:
                return $this->storage[$name] ?? null;
        }
    }

    /**
     * Sets a value to a specified property of the object.
     *
     * @param mixed $name The name of the property to set.
     * @param mixed $value The value to set for the property.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->storage[$name] = $value;
    }

    /**
     * Executes a command detaching from parent and logs the output to log file.
     *
     * @param string|array $cmd The command to execute. Pass an array for proper argument escaping,
     *                          or a string for backward compatibility.
     * @return void
     */
    public function execDetach(string|array $cmd): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            if (is_array($cmd)) {
                $cmd = implode(' ', array_map('escapeshellarg', $cmd));
            } else {
                $cmd = escapeshellcmd($cmd);
            }
            pclose(popen('start /B ' . $cmd, 'r'));
            return;
        }

        if (is_array($cmd)) {
            $program = escapeshellcmd(array_shift($cmd));
            $escapedArgs = array_map('escapeshellarg', $cmd);
            $cmd = $program . ' ' . implode(' ', $escapedArgs);
        } else {
            $cmd = escapeshellcmd($cmd);
        }

        $logDir = self::$logBasePath ?: dirname(__DIR__);
        $logFile = escapeshellarg($logDir . '/nanocore.log');
        shell_exec("{$cmd} >> {$logFile} 2>/dev/null &");
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
