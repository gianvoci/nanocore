<?php

/**
 * NanoCore - A small, lite, mini PHP framework
 *
 * @author Giancarlo Voci
 * @since 2024-05-11
 */

/**
 * Autoloader function to automatically load classes.
 * @release TBA
 *
 * The function registers a callback function to the spl_autoload_register() function
 * which is called whenever a class is not found in the current file scope.
 * The function then tries to include the file based on the class name converted
 * to a file path. For example, the class name 'App\Model\User' would be converted
 * to the file path 'App/Model/User.php'.
 */
// spl_autoload_register(function ($className) {
//   // Definisci il percorso delle classi
//   $classPath = __DIR__ . '/';

//   // Converte il nome della classe in un percorso del file
//   $filePath = $classPath . str_replace('\\', '/', $className) . '.php';

//   // Controlla se il file esiste e lo include
//   if (file_exists($filePath)) {
//     include_once $filePath;
//   } else {
//     // Gestisci eventuali errori di caricamento della classe
//     throw new Exception("Classe non trovata: $className");
//   }
// });

namespace NanoCore;

use ErrorException;

class NanoCore
{
    private array $routes = [];
    private string $basePath;
    private ?string $configFile;
    private array $storage = [];

    public function __construct(string $configFile = 'app.json')
    {
        $this->setErrorHandlers();
        $this->configFile = $configFile;
        $this->basePath = $this->getBasePath();
        $this->setPHPConfig();
        $this->configSet('CORE.ROOT', $this->basePath);
    }

    private function setPHPConfig(): void
    {
        $iniSettings = $this->configGet('PHP.INI') ?? [];
        foreach ($iniSettings as $setting => $value) {
            ini_set($setting, $value);
        }
    }
    private function setErrorHandlers(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        set_exception_handler(function ($exception): void {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(
                [
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                ]
            );
        });
    }

    /**
     * A method to get the base path depending on the PHP server API.
     *
     * @return string The base path determined based on the server API.
     */
    private function getBasePath()
    {
        if (php_sapi_name() === 'cli') {
            return getcwd();
        } else {
            return dirname($_SERVER['SCRIPT_NAME']);
        }
    }

    /**
     * A method to add a route to the routes array after adjusting the path.
     *
     * @param mixed $method The HTTP method of the route.
     * @param string $path The path of the route.
     * @param mixed $handler The handler for the route.
     */
    public function addRoute($method, $path, $handler): void
    {
        // Rimuovi il percorso della sottocartella dalla route se presente
        $path = str_replace($this->basePath, '', $path);
        $path = str_replace('/', '', $path);

        $this->routes[$method][$path] = $handler;
    }

    /**
     * A method to run the application logic based on the request method and route.
     *
     * @throws \Exception When an error occurs during the application execution.
     * @return mixed The response from the handler.
     */
    public function run()
    {
        try {
            if (php_sapi_name() !== 'cli') {
                $method = $_SERVER['REQUEST_METHOD'];
                $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $params);
            } else {
                $method = 'GET';
                $uri = $_SERVER['argv'][1];
                $params = array_slice($_SERVER['argv'], 2);
            }

            $uri = str_replace('/', '', $uri);

            if (isset($this->routes[$method])) {
                foreach ($this->routes[$method] as $route => $handler) {
                    // Match the route segments
                    if ($route === $uri) {
                        if (!is_callable($handler)) {
                            throw new \Exception('Handler for route not callable', 500);
                        }

                        return $handler($this, $params);
                    }
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
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine(),
            ]);
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
    private function loadConfig()
    {
        if (!file_exists($this->configFile)) {
            file_put_contents($this->configFile, '{}');
        }

        $contents = @file_get_contents($this->configFile);
        return json_decode($contents ?? '{}', true);
    }

    /**
     * A method to save the configuration data to a file.
     *
     * @param mixed $data The data to be saved.
     * @return void
     */
    private function saveConfig($data): void
    {
        file_put_contents($this->configFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Retrieves a configuration value for a specified key.
     *
     * @param string $key The key to retrieve the value for.
     * @return mixed The value associated with the key.
     */
    public function configGet($name)
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
     * @throws Exception When there is an error saving the configuration.
     */
    public function configSet(string $prop, $value): void
    {
        $config = $this->loadConfig();

        $path = explode('.', $prop);
        $data = &$config;
        foreach ($path as $prop) {
            if (!isset($data[$prop])) {
                $data[$prop] = [];
            }
            $data = &$data[$prop];
        }

        $data = $value;

        $this->saveConfig($config);
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
    public static function curlRequest(string $url, array $options = [])
    {
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

        // Configura il metodo HTTP
        $curlopt[CURLOPT_CUSTOMREQUEST] = strtoupper($options['method']);

        if (!empty($options['params'])) {
            if ($options['method'] === 'GET' && !is_null($options['params'])) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($options['params']);
            } else {
                $curlopt[CURLOPT_POSTFIELDS] = $options['params'];
            }
        }

        // Aggiungi gli headers se forniti
        if (!empty($options['headers'])) {
            $curlopt[CURLOPT_HTTPHEADER] = $options['headers'];
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, $curlopt);

        for ($retry = 0; $retry < 5 && ($response = curl_exec($ch)) === false; $retry++);

        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception("{\"endpoint\": \"$url\", \"error\": \"$error\"}");
        }

        // Prova a decodificare la risposta JSON
        return ($decodedResponse = json_decode($response)) !== null
          ? $decodedResponse
          : $response;
    }

    /**
     * Retrieves the body content from the input stream and decodes it as JSON if possible.
     *
     * @return mixed The decoded JSON content or the raw content if decoding fails.
     */
    public function getBodyRequest()
    {
        $content = file_get_contents('php://input');
        return  json_decode($content, true) ?? $content;
    }

    /**
     * Renders HTML content by replacing placeholders with provided data.
     *
     * @param string $filename The path to the HTML template file.
     * @param array $data An associative array containing data to replace in the template.
     * @return string The rendered HTML content.
     */
    public function renderHtml(string $filename, array $data = []): string
    {
        $tpl = file_get_contents($filename);

        return str_replace(array_keys($data), array_values($data), $tpl);
    }

    /**
     * Retrieves the specified property of nanocore.
     *
     * @param string $name The name of the property to retrieve.
     * @return mixed The value of the property or the result of the method execution.
     */
    public function __get($name)
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
     * @param string $cmd The command to execute.
     * @return void
     */
    public function execDetach(string $cmd): void
    {
        $cmd = escapeshellcmd($cmd);
        $logFile = escapeshellarg($this->basePath . 'nanocore.log');
        shell_exec("{$cmd} >>/dev/null 2>&1 >> {$logFile} &");
        flush();
        ob_flush();
    }
}
