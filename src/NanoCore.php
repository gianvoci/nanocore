<?php

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
    private array $storage = [];

    public function __construct(string $configFile = 'app.json')
    {
        $this->setErrorHandlers();
        $this->configFile = $this->validateConfigPath($configFile);
        $this->basePath = $this->getBasePath();
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

        // Only enforce .json extension for new files — existing files are accepted as-is
        if (!file_exists($configFile) && !str_ends_with($configFile, '.json')) {
            throw new \Exception("Config file must be a .json file");
        }

        return $configFile;
    }
    private function setErrorHandlers(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        set_exception_handler(function ($exception): void {
            $status = (int)$exception->getCode();
            if ($status < 100 || $status > 599) {
                $status = 500;
            }

            header('Content-Type: application/json');
            http_response_code($status);
            echo json_encode(
                [
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                ]
            );
        });
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
        $path = preg_replace('#/+#', '/', $path);
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
            $path = substr($path, strlen($this->basePath));
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

                    if (!is_callable($route['handler'])) {
                        throw new \Exception('Handler for route not callable', 500);
                    }

                    return $route['handler']($this, $finalParams);
                }
            }

            throw new \Exception('Route not found', 404);
        } catch (\Exception $exception) {
            header('Content-Type: application/json');
            $status = (int)$exception->getCode();
            if ($status < 100 || $status > 599) {
                $status = 500;
            }
            http_response_code($status);
            echo json_encode([
                'error' => $exception->getMessage(),
                'code'  => $exception->getCode(),
            ]);
            return null;
        }
    }
    ################
    # CONFIG MANAGER
    ################

    /**
     * A method to load and parse the configuration data from a file.
     *
     * @return mixed The parsed configuration data as an associative array.
     */
    private function loadConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        if (!file_exists($this->configFile)) {
            file_put_contents($this->configFile, '{}');
        }

        $contents = @file_get_contents($this->configFile);
        $this->configCache = json_decode($contents ?: '{}', true) ?? [];
        return $this->configCache;
    }

    /**
     * Atomically save configuration data to file.
     */
    private function saveConfig(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmpFile = tempnam(dirname($this->configFile), 'nc_cfg_');
        if ($tmpFile === false) {
            throw new \Exception("Failed to create temporary config file");
        }

        $written = file_put_contents($tmpFile, $json);
        if ($written !== false) {
            rename($tmpFile, $this->configFile);
            $this->configCache = $data;
        } else {
            @unlink($tmpFile);
        }
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
     * Validate that a URL uses an allowed scheme and does not point to a restricted network.
     */
    private static function validateUrl(string $url): void
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
    private static function validateIpNotRestricted(string $ip): void
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
     *                       - 'method': The HTTP method to use for the request. Defaults to 'GET'.
     *                       - 'params': The parameters to include in the request. Defaults to an empty array.
     *                       - 'headers': The headers to include in the request. Defaults to an empty array.
     * @throws \Exception When an error occurs during the cURL request.
     * @return mixed The response from the cURL request, decoded as JSON if possible.
     */
    public static function curlRequest(string $url, array $options = []): mixed
    {
        self::validateUrl($url);

        $curlopt = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_MAXREDIRS      => 5,
        ];

        // merge defaults with options
        $options = array_merge([
            'method'  => 'GET',
            'params'  => [],
            'headers' => [],
        ], $options);

        // Configure HTTP method
        $curlopt[CURLOPT_CUSTOMREQUEST] = strtoupper($options['method']);

        if (!empty($options['params'])) {
            if ($options['method'] === 'GET') {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($options['params']);
            } else {
                $curlopt[CURLOPT_POSTFIELDS] = $options['params'];
            }
        }

        // Add headers if provided
        if (!empty($options['headers'])) {
            $curlopt[CURLOPT_HTTPHEADER] = $options['headers'];
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, $curlopt);

        $response = false;
        for ($retry = 0; $retry < 5; $retry++) {
            if ($retry > 0) {
                usleep(100000 * $retry);
                curl_reset($ch);
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

        // Decode JSON when valid, otherwise return raw response
        $decoded = json_decode($response, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $response;
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
        $rootPath = realpath($this->basePath ?: '.');
        if ($rootPath === false || !str_starts_with($realPath, $rootPath)) {
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

        return str_replace(array_keys($escapedData), array_values($escapedData), $tpl);
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
    public function __set($name, $value): void
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
        if (is_array($cmd)) {
            $program = escapeshellcmd(array_shift($cmd));
            $escapedArgs = array_map('escapeshellarg', $cmd);
            $cmd = $program . ' ' . implode(' ', $escapedArgs);
        } else {
            $cmd = escapeshellcmd($cmd);
        }

        $basePath = rtrim($this->basePath, '/');
        $logPath = $basePath === '' ? 'nanocore.log' : $basePath . '/nanocore.log';
        $logFile = escapeshellarg($logPath);
        shell_exec("{$cmd} >>/dev/null 2>&1 >> {$logFile} &");
        flush();
        ob_flush();
    }
}
