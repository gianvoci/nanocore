<?

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

class NanoCore
{
  private array $routes = [];
  private string $basePath;
  private ?string $configFile;


  public function __construct(string $configFile = 'app.json')
  {
    $this->_setErrorHandlers();
    $this->configFile = $configFile;
    $this->basePath = $this->getBasePath();
    $this->_setPHPConfig();
    $this->Set('CORE.ROOT', $this->basePath);
  }

  private function _setPHPConfig(): void
  {
    $iniSettings = $this->Get('PHP.INI');
    foreach ($iniSettings as $setting => $value) {
      ini_set($setting, $value);
    }
  }
  private function _setErrorHandlers(): void
  {
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    set_exception_handler(function ($exception) {
      header('Content-Type: application/json');
      http_response_code(500);
      echo json_encode(
        [
          'message' => $exception->getMessage(),
          'code' => $exception->getCode(),
          'file' => $exception->getFile(),
          'line' => $exception->getLine(),
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
  public function addRoute($method, $path, $handler)
  {
    // Rimuovi il percorso della sottocartella dalla route se presente
    $path = str_replace($this->basePath, '', $path);

    $this->routes[$method][$path] = $handler;
  }

  /**
   * A method to run the application logic based on the request method and route.
   *
   * @throws \Exception When an error occurs during the application execution.
   */
  public function run()
  {
    try {
      if (php_sapi_name() === 'cli') {
        $method = 'GET';
        $path = $_SERVER['argv'][1] ?? '/';
        $params = array_slice($_SERVER['argv'], 2);
      } else {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace($this->basePath, '', $path);
        $params = [];
        parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $params);
      }

      $pathSplitted = explode('/', $path);
      array_shift($pathSplitted);

      foreach ($this->routes[$method] as $route => $handler) {
        if (strpos($route, "/{$pathSplitted[0]}") === 0) {
          if (is_callable($handler)) {
            array_shift($pathSplitted);
            return call_user_func($handler, $this, $pathSplitted, $params);
          } else {
            throw new Exception("Handler for route not callable");
          }
        }
      }
      throw new Exception('Route not found');
    } catch (\Exception $e) {
      throw $e;
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
  private function _loadConfig()
  {
    if (!file_exists($this->configFile))
      file_put_contents($this->configFile, '{}');

    $contents = @file_get_contents($this->configFile);
    return json_decode($contents ?? '{}', true);
  }

  /**
   * A method to save the configuration data to a file.
   *
   * @param mixed $data The data to be saved.
   * @return void
   */
  private function _saveConfig($data)
  {
    file_put_contents($this->configFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Retrieves a configuration value for a specified key.
   *
   * @param string $key The key to retrieve the value for.
   * @return mixed The value associated with the key.
   */
  public function Get($name)
  {
    $data = $this->_loadConfig();

    $path = explode(".", $name);
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
  public function Set(string $prop, $value)
  {
    $config = $this->_loadConfig();

    $path = explode(".", $prop);
    $data = &$config;
    foreach ($path as $prop) {
      if (!isset($data[$prop])) {
        $data[$prop] = [];
      }
      $data = &$data[$prop];
    }

    $data = $value;

    $this->_saveConfig($config);
  }



  /**
   * Makes a cURL request to the specified URL with customizable method, parameters, and headers.
   *
   * @param string $url The URL to which the cURL request is made.
   * @param string $method The HTTP method to be used for the request. Default is 'GET'.
   * @param array $params An array of parameters to be sent with the request. Default is an empty array.
   * @param array $headers An array of headers to be included in the request. Default is an empty array.
   * @return mixed The decoded JSON response if successful, otherwise the raw response.
   */
  public function CurlRequest($url, $method = 'GET', $params = [], $headers = [])
  {
    $ch = curl_init($url);

    $options = [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_AUTOREFERER => true,
      CURLOPT_CONNECTTIMEOUT => 30,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_MAXREDIRS => 5,
    ];

    // Configura il metodo HTTP
    $options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);

    if (!empty($params)) {
      $headers[] = 'Content-Type: application/json';
      if ($method === 'GET') {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($params);
      } else
        $options[CURLOPT_POSTFIELDS] = json_encode($params);
    }

    // Aggiungi gli headers se forniti
    if (!empty($headers)) {
      $options[CURLOPT_HTTPHEADER] = $headers;
    }

    curl_setopt_array($ch, $options);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    for ($retry = 0; $retry < 5 && ($response = curl_exec($ch)) === false; $retry++);

    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
      throw new \Exception(
        json_encode([
          'error' => [
            'endpoint' => $url,
            'description' => $error
          ]
        ])
      );
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
  public function GetBodyRequest()
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
  public function RenderHtml(string $filename, array $data = []): string
  {
    $tpl = file_get_contents($filename);

    return str_replace(array_keys($data), array_values($data), $tpl);
  }
}
