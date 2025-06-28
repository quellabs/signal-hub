<?php
	
	namespace Quellabs\Canvas;
	
	use Dotenv\Dotenv;
	use Dotenv\Exception\InvalidEncodingException;
	use Dotenv\Exception\InvalidFileException;
	use Dotenv\Exception\InvalidPathException;
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\Canvas\AOP\AspectDispatcher;
	use Quellabs\Canvas\Configuration\Configuration;
	use Quellabs\Canvas\Discover\ConfigurationProvider;
	use Quellabs\Canvas\Discover\DiscoverProvider;
	use Quellabs\Canvas\Discover\KernelProvider;
	use Quellabs\Canvas\Discover\RequestProvider;
	use Quellabs\Canvas\Discover\SessionInterfaceProvider;
	use Quellabs\Canvas\Exceptions\RouteNotFoundException;
	use Quellabs\Canvas\Legacy\LegacyBridge;
	use Quellabs\Canvas\Legacy\LegacyHandler;
	use Quellabs\Canvas\Routing\AnnotationResolver;
	use Quellabs\DependencyInjection\Container;
	use Quellabs\Discover\Discover;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;
	use Symfony\Component\HttpFoundation\Session\Session;
	
	class Kernel {
		
		private Discover $discover; // Service discovery
		private AnnotationReader $annotationsReader; // Annotation reading
		private Configuration $configuration;
		private ?array $contents_of_app_php = null;
		private bool $legacyEnabled;
		private ?LegacyHandler $legacyFallbackHandler;
		private Container $dependencyInjector;
		
		/**
		 * Kernel constructor
		 * @param array $configuration
		 */
		public function __construct(array $configuration = []) {
			// Register Discovery service
			$this->discover = new Discover();
			
			// Read the environment file
			$this->loadEnvironmentFile();
			
			// Store the configuration array
			$this->configuration = new Configuration(array_merge($this->getConfigFile(), $configuration));
			
			// Register Annotations Reader
			$this->annotationsReader = $this->createAnnotationReader();
			
			// Instantiate Dependency Injector and register default providers
			$this->dependencyInjector = new Container();
			$this->dependencyInjector->register(new KernelProvider($this));
			$this->dependencyInjector->register(new ConfigurationProvider($this->configuration));
			$this->dependencyInjector->register(new DiscoverProvider($this->discover));
			
			// Initialize legacy support
			$this->initializeLegacySupport();
			
			// Add a custom exception handler for some nicer exception messages
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
		 * Returns the Configuration object
		 * @return Configuration
		 */
		public function getConfiguration(): Configuration {
			return $this->configuration;
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
		 * @return LegacyHandler|null
		 */
		public function getLegacyHandler(): ?LegacyHandler {
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
		 * Process an HTTP request through the controller system and return the response
		 * @param Request $request The incoming HTTP request object
		 * @return Response HTTP response to be sent back to the client
		 */
		public function handle(Request $request): Response {
			// Check if session exists, create if needed
			if (!$request->hasSession()) {
				$session = new Session();
				$request->setSession($session);
			}
			
			// Register providers with dependency injector for this request lifecycle
			$requestProvider = new RequestProvider($request);
			$sessionInterfaceProvider = new SessionInterfaceProvider($request->getSession());
			$this->dependencyInjector->register($requestProvider);
			$this->dependencyInjector->register($sessionInterfaceProvider);
			
			try {
				// Initialize URL resolver with annotation-based routing capabilities
				$urlResolver = new AnnotationResolver($this);
				
				// Attempt to resolve the incoming request URL to route data
				// This maps the URL to controller class, method name, and parameters
				$urlData = $urlResolver->resolve($request);
				
				// Handle case where no route matches the request URL
				if (!$urlData && $this->legacyEnabled && $this->legacyFallbackHandler) {
					// Legacy system is enabled - attempt fallback to legacy routing system
					try {
						return $this->legacyFallbackHandler->handle($request);
					} catch (RouteNotFoundException $e) {
						// Both new and legacy routing failed - return 404 with legacy context
						return $this->createNotFoundResponse($request, true);
					}
				}
				
				// No route found and legacy system disabled - return standard 404
				if (!$urlData) {
					return $this->createNotFoundResponse($request, false);
				}
				
				// Route successfully resolved - execute the mapped controller method
				return $this->executeCanvasRoute($request, $urlData);
				
			} catch (\Exception $e) {
				// Handle any unexpected exceptions during request processing
				return $this->createErrorResponse($e);
			} finally {
				// Critical cleanup: Always unregister to prevent memory leaks
				// and ensure the dependency injector doesn't retain stale request references
				// This executes regardless of how the try block exits (return, exception, etc.)
				$this->dependencyInjector->unregister($sessionInterfaceProvider);
				$this->dependencyInjector->unregister($requestProvider);
			}
		}
		
		/**
		 * Creates and configures an AnnotationReader instance with optimized caching settings.
		 * @return AnnotationReader Configured annotation reader instance
		 */
		private function createAnnotationReader(): AnnotationReader {
			// Initialize the annotation reader configuration object
			$config = new \Quellabs\AnnotationReader\Configuration();
			
			// Check if we're NOT in debug mode (i.e., in production or staging)
			if (!$this->configuration->getAs('debug_mode', 'bool', false)) {
				// Get the project root directory path for cache storage
				$rootPath = $this->discover->getProjectRoot();
				
				// Enable annotation caching for better performance in production
				$config->setUseAnnotationCache(true);
				
				// Set the cache directory path within the project's storage folder
				$config->setAnnotationCachePath($rootPath . "/storage/annotations");
			}
			
			// Create and return the configured AnnotationReader instance
			return new AnnotationReader($config);
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
			} catch (\ReflectionException $e) {
				return $this->createErrorResponse($e);
			}
		}
		
		/**
		 * Create a 404 Not Found response
		 * @param Request $request The Request object
		 * @param bool $legacyAttempted Whether legacy fallthrough was attempted
		 * @return Response
		 */
		private function createNotFoundResponse(Request $request, bool $legacyAttempted = false): Response {
			$isDevelopment = $this->configuration->getAs('debug_mode', 'bool', false);
			$legacyPath = $this->getDiscover()->resolvePath($this->configuration->get('legacy_path', $this->discover->getProjectRoot() . DIRECTORY_SEPARATOR . 'legacy'));
			$notFoundFile = $legacyPath . '404.php';
			
			if ($isDevelopment) {
				// In development, show helpful debug information
				if ($legacyAttempted) {
					$legacyMessage = "No Canvas route found. Legacy fallback also has no matching file.\n\n";
				} elseif ($this->legacyEnabled) {
					$legacyMessage = "No Canvas route found. No matching legacy file exists.\n\n";
				} else {
					$legacyMessage = "No Canvas route found.\n\n";
				}
				
				if ($this->legacyEnabled && file_exists($notFoundFile)) {
					$customizationHelp = "Custom 404 file found at: {$notFoundFile}\n- This will be used in production mode\n- Or add a Canvas route for this path";
				} elseif ($this->legacyEnabled) {
					$customizationHelp = "To customize this page:\n- Create a 404.php file in your legacy directory ({$legacyPath})\n- Or add a Canvas route for this path";
				} else {
					$customizationHelp = "To customize this page:\n- Add a Canvas route for this path\n- Or enable legacy mode and create a 404.php file";
				}
				
				$content = sprintf(
					"404 Not Found\n\nRequested: %s %s\n\n%s%s",
					$request->getMethod(),
					$request->getPathInfo(),
					$legacyMessage,
					$customizationHelp
				);
				
				return new Response($content, Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain']);
			}
			
			// In production, try to include a custom 404.php file if it exists and legacy is enabled
			if ($this->legacyEnabled && file_exists($notFoundFile)) {
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
			$isDevelopment = $this->configuration->getAs('debug_mode', 'bool', false);
			
			if ($isDevelopment) {
				// In development, show detailed error information as HTML
				$content = $this->renderDebugErrorPageContent($exception);
				return new Response($content, Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'text/html']);
			}
			
			// In production, try to include a simple 500.php file if it exists
			$legacyPath = $this->configuration->get('legacy_path', 'legacy/');
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
			} catch (InvalidEncodingException|InvalidFileException|InvalidPathException $e) {
			}
		}
		
		/**
		 * Initialize the legacy support system
		 * @return void
		 */
		private function initializeLegacySupport(): void {
			// Check if legacy fallthrough is enabled
			$this->legacyEnabled = $this->configuration->getAs('legacy_enabled', 'bool', false);
			
			if ($this->legacyEnabled) {
				// Only initialize bridge when legacy support is enabled
				LegacyBridge::initialize($this->dependencyInjector);
				
				// Fetch the legacy path
				$legacyPath = $this->configuration->get('legacy_path', $this->discover->getProjectRoot() . '/legacy/');
				
				// Fetch the legacy path
				$preprocessingEnabled = $this->configuration->get('legacy_preprocessing', true);
				
				// Create the fallthrough handler
				$this->legacyFallbackHandler = new LegacyHandler($this, $legacyPath, $preprocessingEnabled);
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