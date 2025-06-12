<?php
    
    namespace Quellabs\Canvas;
    
    use Dotenv\Dotenv;
    use Dotenv\Exception\InvalidEncodingException;
    use Dotenv\Exception\InvalidFileException;
    use Dotenv\Exception\InvalidPathException;
    use Quellabs\AnnotationReader\AnnotationReader;
    use Quellabs\AnnotationReader\Configuration;
    use Quellabs\Canvas\AOP\AspectDispatcher;
    use Quellabs\Canvas\Routing\AnnotationResolver;
    use Quellabs\DependencyInjection\Container;
    use Quellabs\Discover\Discover;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    
    class Kernel {
	    
	    private Discover $discover; // Service discovery
	    private AnnotationReader $annotationsReader; // Annotation reading
	    private array $configuration;
	    private ?array $contents_of_app_php = null;
	    
	    /**
	     * Kernel constructor
	     * @param array $configuration
	     */
	    public function __construct(array $configuration=[]) {
		    // Register Discovery service
		    $this->discover = new Discover();
		    
		    // Read the environment file
		    $this->loadEnvironmentFile();
		    
		    // Store the configuration array
		    $this->configuration = array_merge($this->getConfigFile(), $configuration);
		    
		    // Register Annotations Reader
		    $annotationsReaderConfig = new Configuration();
		    $this->annotationsReader = new AnnotationReader($annotationsReaderConfig);

		    // Zet een custom exception handler voor wat mooiere exceptie meldingen
		    set_exception_handler([$this, 'customExceptionHandler']);
	    }
	    
		/**
	     * Returns the Service Discovery object
		 * This also provides PSR-4 utilities
	     * @return Discover
	     */
	    public function getDiscover(): Discover {
		    return $this->discover;
	    }
	    
	    /**
	     * Returns the AnnotationReader object
	     * @return AnnotationReader
	     */
	    public function getAnnotationsReader(): AnnotationReader {
		    return $this->annotationsReader;
	    }
	    
	    /**
	     * Custom handler voor onafgehandelde excepties
	     * @param \Throwable $exception
	     * @return void
	     */
	    public function customExceptionHandler(\Throwable $exception): void {
		    $errorCode = $exception->getCode();
		    $errorMessage = $exception->getMessage();
		    $errorFile = $exception->getFile();
		    $errorLine = $exception->getLine();
		    $trace = $exception->getTraceAsString();
		    
		    // Extract variables to template
		    extract(compact('errorCode', 'errorMessage', 'errorFile', 'errorLine', 'trace'));
		    
		    // Buffer de output
		    ob_start();
		    include __DIR__ . '/Templates/error.html.php';
		    echo ob_get_clean();
		    
		    exit(1);
	    }
	    
	    /**
	     * Returns the entire configuration array as passed in the constructor
	     * @return array
	     */
		public function getConfiguration(): array {
			return $this->configuration;
		}
	    
	    /**
	     * Check if a configuration key exists
	     * @param string $key Configuration key
	     * @return bool
	     */
	    public function hasConfig(string $key): bool {
		    return array_key_exists($key, $this->configuration);
	    }
	    
	    /**
	     * Get a specific configuration value
	     * @param string $key Configuration key
	     * @param mixed|null $default Default value if key doesn't exist
	     * @return mixed
	     */
	    public function getConfig(string $key, mixed $default = null): mixed {
		    return $this->configuration[$key] ?? $default;
	    }
	    
	    /**
	     * Get configuration value with type casting
	     * @param string $key Configuration key
	     * @param string $type Type to cast to ('string', 'int', 'float', 'bool', 'array')
	     * @param mixed|null $default Default value
	     * @return mixed
	     */
	    public function getConfigAs(string $key, string $type, mixed $default = null): mixed {
		    $value = $this->getConfig($key, $default);
		    
		    if ($value === null) {
			    return $default;
		    }
		    
		    switch (strtolower($type)) {
			    case 'string':
				    return (string) $value;
					
			    case 'int':
			    case 'integer':
				    return (int) $value;
					
			    case 'float':
			    case 'double':
				    return (float) $value;
					
			    case 'bool':
			    case 'boolean':
				    // Handle common boolean strings
				    if (is_string($value)) {
					    return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
				    }
					
				    return (bool) $value;
					
			    case 'array':
				    // Handle comma-separated strings
				    if (is_string($value)) {
					    return array_map('trim', explode(',', $value));
				    }
					
				    return is_array($value) ? $value : [$value];
					
			    default:
				    return $value;
		    }
	    }
		
	    /**
	     * Get all configuration keys
	     * @return array
	     */
	    public function getConfigKeys(): array {
		    return array_keys($this->configuration);
	    }
		
		/**
	     * Lookup the url and returns information about it
	     * @param Request $request The incoming HTTP request object
	     * @return Response HTTP response to be sent back to the client
	     */
	    public function handle(Request $request): Response {
			// Instantiate the URL resolver
		    $urlResolver = new AnnotationResolver($this);
			
			// Instantiate Dependency Injector
		    $dependencyInjector = new Container();
		    
		    // Retrieve URL data using the resolver service
		    // This maps the URL to controller, method and parameters
		    $urlData = $urlResolver->resolve($request);
		    
		    // If no matching route was found, return a 404 Not Found response
		    if (!$urlData) {
			    return new Response('', Response::HTTP_NOT_FOUND);
		    }
		    
		    // Execute the appropriate controller method based on route information
		    try {
			    // Get the controller instance from the dependency injection container
			    // $urlData["controller"] contains the controller class name
			    $controller = $dependencyInjector->get($urlData["controller"]);

				// Create aspect-aware dispatcher
			    $aspectDispatcher = new AspectDispatcher($this->annotationsReader, $this->di);
				
			    return $aspectDispatcher->dispatch(
					$request,
				    $controller,
				    $urlData["method"],
				    $urlData["variables"]
			    );
		    } catch (\Exception $e) {
			    // If any exception occurs during execution, return it as the response
			    // with the exception message as content and exception code as HTTP status
			    return new Response($e->getMessage(), 500);
		    }
	    }
	    
	    /**
	     * Load app.php
	     * @return array
	     */
	    private function getConfigFile(): array {
			// Fetch from cache if we can
		    if ($this->contents_of_app_php !== null) {
				return $this->contents_of_app_php;
		    }

		    // Fetch the project root
		    $projectRoot = $this->discover->getProjectRoot();
		    
		    // If the config file does not exist, do not attempt to load it
		    if (!file_exists($projectRoot . '/config/app.php')) {
			    $this->contents_of_app_php = [];
			    return [];
		    }
		    
		    // Otherwise, grab the contents
		    $this->contents_of_app_php = require $projectRoot . '/config/app.php';
		    
			// And return them
		    return $this->contents_of_app_php;
	    }
		
		/**
	     * Loads the .env file into $_ENV, $_SERVER and getenv()
	     * @return void
	     */
	    private function loadEnvironmentFile(): void {
		    try {
			    // Fetch the project root
			    $projectRoot = $this->discover->getProjectRoot();
			    
			    // Create a new Dotenv instance pointing to project root
			    $dotenv = Dotenv::createImmutable($projectRoot);
			    
			    // Load variables into $_ENV, $_SERVER, and getenv()
			    $dotenv->load();
			} catch (InvalidEncodingException |InvalidFileException | InvalidPathException $e) {
		    }
	    }
    }