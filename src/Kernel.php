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
		    $this->loadEnvironmentFile();
		    
		    // Config for AnnotationsReader
		    $annotationsReaderConfig = new Configuration();
		    
		    // Register all services
		    $this->di = new Container();
		    $this->annotationsReader = new AnnotationReader($annotationsReaderConfig);
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

				// Create aspect-aware dispatcher
			    $aspectDispatcher = new AspectDispatcher($this->annotationsReader, $this->di);
			    
			    return $aspectDispatcher->dispatch(
				    $controller,
				    $urlData["method"],
				    $urlData["variables"]
			    );
		    } catch (\Exception $e) {
			    // If any exception occurs during execution, return it as the response
			    // with the exception message as content and exception code as HTTP status
			    return new Response($e->getMessage(), $e->getCode());
		    }
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