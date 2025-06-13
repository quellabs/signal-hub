<?php
    
    namespace Quellabs\Canvas;
    
    use Dotenv\Dotenv;
    use Dotenv\Exception\InvalidEncodingException;
    use Dotenv\Exception\InvalidFileException;
    use Dotenv\Exception\InvalidPathException;
    use Quellabs\AnnotationReader\AnnotationReader;
    use Quellabs\AnnotationReader\Configuration;
    use Quellabs\AnnotationReader\Exception\ParserException;
    use Quellabs\Canvas\AOP\AspectDispatcher;
    use Quellabs\Canvas\Exceptions\RouteNotFoundException;
    use Quellabs\Canvas\Legacy\LegacyBridge;
    use Quellabs\Canvas\Legacy\LegacyFallthroughHandler;
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
	    private bool $legacyEnabled;
	    private ?LegacyFallthroughHandler $legacyFallbackHandler;
	    private Container $dependencyInjector;
	    
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
		    
		    // Instantiate Dependency Injector
		    $this->dependencyInjector = new Container();
		    
		    // Initialize legacy support
		    $this->initializeLegacySupport();
		    
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
	     * Returns true if legacy fallback is enabled
	     * @return bool
	     */
		public function isLegacyEnabled(): bool {
			return $this->legacyEnabled;
		}
	    
	    /**
	     * Returns the legacy fallback handler object
	     * @return LegacyFallthroughHandler|null
	     */
	    public function getLegacyHandler(): ?LegacyFallthroughHandler {
		    return $this->legacyFallbackHandler;
	    }
	    
	    /**
	     * Custom handler for exceptions
	     * @param \Throwable $exception
	     * @return void
	     */
	    public function customExceptionHandler(\Throwable $exception): void {
		    // Clear any previous output
		    if (ob_get_level()) {
			    ob_end_clean();
		    }
		    
			// Create error response using the existing method
		    $response = $this->createErrorResponse($exception);

			// Send the response
		    $response->send();
		    
			// Exit
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
			
			try {
				// Retrieve URL data using the resolver service
				// This maps the URL to controller, method and parameters
				$urlData = $urlResolver->resolve($request);
				
				// If no matching route was found and legacy is enabled, try legacy fallthrough
				if (!$urlData && $this->legacyEnabled && $this->legacyFallbackHandler) {
					try {
						return $this->legacyFallbackHandler->handle($request);
					} catch (RouteNotFoundException $e) {
						// Legacy fallthrough also failed, return 404
						return $this->createNotFoundResponse($request);
					}
				}
				
				// If no matching route was found and legacy is disabled, return 404
				if (!$urlData) {
					return $this->createNotFoundResponse($request);
				}
				
				// Execute the appropriate controller method based on route information
				return $this->executeCanvasRoute($request, $urlData);
			} catch (\Exception $e) {
				return $this->createErrorResponse($e);
			}
	    }
	    
	    /**
	     * Execute a Canvas route
	     * @param Request $request
	     * @param array $urlData
	     * @return Response
	     */
	    private function executeCanvasRoute(Request $request, array $urlData): Response {
		    // Get the controller instance from the dependency injection container
		    $controller = $this->dependencyInjector->get($urlData["controller"]);
		    
		    // Create aspect-aware dispatcher
		    $aspectDispatcher = new AspectDispatcher($this->annotationsReader, $this->dependencyInjector);
		    
			// Run the request through the aspect dispatcher
		    try {
			    return $aspectDispatcher->dispatch(
				    $request,
				    $controller,
				    $urlData["method"],
				    $urlData["variables"]
			    );
		    } catch (ParserException|\ReflectionException $e) {
				return $this->createErrorResponse($e);
		    }
	    }
	    
	    /**
	     * Create a 404 Not Found response
	     * @param Request $request
	     * @return Response
	     */
	    private function createNotFoundResponse(Request $request): Response {
		    $isDevelopment = $this->getConfigAs('debug_mode', 'bool', false);
		    
		    if ($isDevelopment) {
			    // In development, show helpful debug information
			    $content = sprintf(
				    "404 Not Found\n\nRequested: %s %s\n\nTo customize this page:\n- Create a 404.php file in your legacy directory\n- Or add a Canvas route for this path",
				    $request->getMethod(),
				    $request->getPathInfo()
			    );
				
			    return new Response($content, Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain']);
		    }
		    
		    // In production, try to include a simple 404.php file if it exists
		    $legacyPath = $this->getConfig('legacy_path', 'legacy/');
		    $notFoundFile = $legacyPath . '404.php';
		    
		    if (file_exists($notFoundFile)) {
			    ob_start();
			    include $notFoundFile;
			    $content = ob_get_clean();
			    return new Response($content, Response::HTTP_NOT_FOUND);
		    }
		    
		    // Ultimate fallback - simple text
		    return new Response('Page not found', Response::HTTP_NOT_FOUND);
	    }
	    
	    /**
	     * Create an error response from an exception
	     * @param \Throwable $exception
	     * @return Response
	     */
	    private function createErrorResponse(\Throwable $exception): Response {
		    $isDevelopment = $this->getConfigAs('debug', 'bool', false);
		    
		    if ($isDevelopment) {
			    // In development, show detailed error information as HTML
			    $content = $this->renderDebugErrorPageContent($exception);
			    return new Response($content, Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'text/html']);
		    }
		    
		    // In production, try to include a simple 500.php file if it exists
		    $legacyPath = $this->getConfig('legacy_path', 'legacy/');
		    $errorFile = $legacyPath . '500.php';
		    
		    if (file_exists($errorFile)) {
			    ob_start();
			    include $errorFile;
			    $content = ob_get_clean();
			    return new Response($content, Response::HTTP_INTERNAL_SERVER_ERROR);
		    }
		    
		    // Ultimate fallback - simple HTML page
		    $content = $this->renderProductionErrorPageContent();
		    return new Response($content, Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'text/html']);
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
	    
	    /**
	     * Initialize the legacy support system
	     * @return void
	     */
	    private function initializeLegacySupport(): void {
		    // Check if legacy fallthrough is enabled
		    $this->legacyEnabled = $this->getConfigAs('legacy_enabled', 'bool', false);
		    
		    if ($this->legacyEnabled) {
			    // Only initialize bridge when legacy support is enabled
			    LegacyBridge::initialize($this->dependencyInjector);
			    
			    // Fetch the legacy path
			    $legacyPath = $this->getConfig('legacy_path', 'legacy/');
			    
			    // If legacy_path is relative, make it relative to project root
			    if (!str_starts_with($legacyPath, '/')) {
				    $legacyPath = $this->discover->getProjectRoot() . '/' . $legacyPath;
			    }
			    
			    // Create the fallthrough handler
			    $this->legacyFallbackHandler = new LegacyFallthroughHandler($legacyPath);
		    }
	    }
	    
	    /**
	     * Render detailed error page content for development
	     * @param \Throwable $exception
	     * @return string
	     */
	    private function renderDebugErrorPageContent(\Throwable $exception): string {
		    $errorCode = $exception->getCode();
		    $errorMessage = $exception->getMessage();
		    $errorFile = $exception->getFile();
		    $errorLine = $exception->getLine();
		    $trace = $exception->getTraceAsString();
		    
		    return "<!DOCTYPE html>
<html>
<head>
    <title>Canvas Framework Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .error-box { background: white; padding: 20px; border-left: 5px solid #dc3545; }
        .error-title { color: #dc3545; margin: 0 0 20px 0; }
        .error-message { font-size: 18px; margin-bottom: 20px; }
        .error-details { background: #f8f9fa; padding: 15px; border-radius: 4px; }
        .trace { background: #2d2d2d; color: #f8f8f2; padding: 15px; overflow-x: auto; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class='error-box'>
        <h1 class='error-title'>Canvas Framework Error</h1>
        <div class='error-message'>" . htmlspecialchars($errorMessage) . "</div>
        <div class='error-details'>
            <strong>File:</strong> " . htmlspecialchars($errorFile) . "<br>
            <strong>Line:</strong> " . $errorLine . "<br>
            <strong>Code:</strong> " . $errorCode . "
        </div>
        <h3>Stack Trace:</h3>
        <pre class='trace'>" . htmlspecialchars($trace) . "</pre>
    </div>
</body>
</html>";
	    }
	    
	    /**
	     * Render generic error page content for production
	     * @return string
	     */
	    private function renderProductionErrorPageContent(): string {
		    return "<!DOCTYPE html>
<html>
<head>
    <title>Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; text-align: center; }
        .error-box { background: white; padding: 40px; border-radius: 8px; display: inline-block; }
        .error-title { color: #dc3545; margin: 0 0 20px 0; }
    </style>
</head>
<body>
    <div class='error-box'>
        <h1 class='error-title'>Server Error</h1>
        <p>Something went wrong. Please try again later.</p>
        <p>If the problem persists, please contact support.</p>
    </div>
</body>
</html>";
	    }
    }