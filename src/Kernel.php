<?php
    
    namespace Quellabs\Canvas;
    
    use Dotenv\Dotenv;
    use Dotenv\Exception\InvalidFileException;
    use Dotenv\Exception\InvalidPathException;
    use Quellabs\AnnotationReader\AnnotationReader;
    use Quellabs\AnnotationReader\Configuration;
    use Quellabs\Canvas\Resolvers\AnnotationResolver;
    use Quellabs\DependencyInjection\Container;
    use Quellabs\Discover\Discover;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    
    class Kernel {
	    
	    private array $configuration;
	    private Discover $discover; // Service discovery
	    private Container $di; // Dependency Injection
	    private AnnotationReader $annotationsReader; // Annotation reading
	    
	    /**
	     * Kernel constructor
	     */
	    public function __construct() {
		    // Zet een custom exception handler voor wat mooiere exceptie meldingen
		    set_exception_handler([$this, 'customExceptionHandler']);
			
			// Register Discovery service
		    $this->discover = new Discover();
		    
		    // Read the environment file
		    $this->configuration = $this->readEnvironmentFile();
		    
		    // Config for AnnotationsReader
		    $annotationsReaderConfig = new Configuration();
		    
		    // Register all services
		    $this->di = new Container();
		    $this->annotationsReader = new AnnotationReader($annotationsReaderConfig);
	    }
	    
	    /**
	     * Returns the parsed contents of the .env file
	     * @return array
	     */
	    public function getConfiguration(): array {
		    return $this->configuration;
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
	     * Returns the Dependency Injection object
	     * @return Container
	     */
	    public function getDi(): Container {
		    return $this->di;
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
	     * Lookup the url and returns information about it
	     * @param Request $request The incoming HTTP request object
	     * @return Response HTTP response to be sent back to the client
	     */
	    public function handle(Request $request): Response {
			// Instantiate the URL resolver
		    $urlResolver = new AnnotationResolver($this);

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
			    $controller = $this->di->get($urlData["controller"]);
			    
			    // Call the method on the controller with the resolved variables using the DI container
			    // The DI container handles method invocation and dependency injection
			    // - $controller: The controller instance
			    // - $urlData["method"]: The controller method to execute
			    // - $urlData["variables"]: Route parameters extracted from the URL
			    return $this->di->invoke($controller, $urlData["method"], $urlData["variables"]);
		    } catch (\Exception $e) {
			    // If any exception occurs during execution, return it as the response
			    // with the exception message as content and exception code as HTTP status
			    return new Response($e->getMessage(), $e->getCode());
		    }
	    }
	    
	    /**
	     * Reads and parses the .env file into an array
	     * Uses the vlucas/phpdotenv library to parse environment variables
	     * Does not load variables into $_ENV or $_SERVER
	     * @return array Array containing all environment variables as key-value pairs
	     * @throws InvalidFileException If the .env file format is invalid
	     * @throws InvalidPathException If the .env file cannot be found
	     */
	    private function readEnvironmentFile(): array {
		    // Create a new Dotenv instance pointing to current directory
		    $dotenv = Dotenv::createImmutable(__DIR__);
		    
		    // Fetch the project root
		    $projectRoot = $this->discover->getProjectRoot();
		    
		    // Read raw contents of the .env file from parent directory
		    $content = file_get_contents($projectRoot . DIRECTORY_SEPARATOR . '.env');
		    
		    // Parse the raw content into an associative array using Dotenv parser
		    return $dotenv->parse($content);
	    }
    }