<?php
    
    namespace Quellabs\ObjectQuel\Kernel;
    
    use Dotenv\Dotenv;
    use Dotenv\Exception\InvalidFileException;
    use Dotenv\Exception\InvalidPathException;
   use Quellabs\ObjectQuel\AnnotationsReader\Annotations\AfterFilter;
   use Quellabs\ObjectQuel\AnnotationsReader\Annotations\BeforeFilter;
   use Quellabs\ObjectQuel\AnnotationsReader\AnnotationsReader;
    use Quellabs\ObjectQuel\Kernel\Resolvers\AnnotationResolver;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    
    include_once("../vendor/autoload.php");
	
    class Kernel {
	   
		private array $configuration;
		private ServiceLocator $serviceLocator;
	    private Autowire $autowire;
	    private AnnotationResolver $urlResolver;
	    
	    /**
	     * Kernel constructor
	     */
		public function __construct() {
			// Zet een custom exception handler voor wat mooiere exceptie meldingen
			set_exception_handler([$this, 'customExceptionHandler']);
			
			// Lees de .env file in
			$this->configuration = $this->readEnvironmentFile();
			
			// Registreert een autoloader functie om klassen automatisch te laden vanuit een gespecificeerde root directory.
			spl_autoload_register(function ($className) {
				if (str_starts_with($className, "Services\\")) {
					$classPath = substr(str_replace("\\", DIRECTORY_SEPARATOR, $className), 9);
					$documentRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . "..");
					$completePath = "{$documentRoot}/{$classPath}.php";
					
					if (file_exists($completePath)) {
						include($completePath);
						return true;
					}
				}
				
				return null;
			});
			
			// Registreer alle services
			$this->serviceLocator = new ServiceLocator($this);
			$this->autowire = new Autowire($this);
			$this->urlResolver = new AnnotationResolver($this);
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
		    
		    // Read raw contents of the .env file from parent directory
		    $content = file_get_contents(__DIR__ . '/../../.env');
		    
		    // Parse the raw content into an associative array using Dotenv parser
		    return $dotenv->parse($content);
	    }
	    
	    /**
	     * Returns the parsed contents of the .env file
	     * @return array
	     */
		public function getConfiguration(): array {
			return $this->configuration;
		}
	    
	    /**
	     * Fetches a container by name, either from a supporting service or by instantiating it
	     * @template T
	     * @param class-string<T> $serviceName The fully qualified class name of the container
	     * @return T|null The container instance
	     */
	    public function getService(string $serviceName): ?object {
			return $this->serviceLocator->getService($serviceName);
	    }
	    
	    /**
	     * Calls a service provider to fetch the desired object
	     * @template T
	     * @param class-string<T> $className
	     * @param ...$args
	     * @return T|null
	     */
	    public function getFromProvider(string $className, ...$args): ?object {
		    return $this->serviceLocator->getFromProvider($className, $args);
	    }
	    
	    /**
	     * Returns the service locator
	     * @return ServiceLocator
	     */
		public function getServiceLocator(): ServiceLocator {
			return $this->serviceLocator;
		}
	    
	    /**
	     * Use reflection to autowire service classes into the constructor of an object
	     * @param string $className
	     * @param string $methodName
	     * @param array $matchingVariables
	     * @return array
	     */
		public function autowireClass(string $className, string $methodName="", array $matchingVariables=[]): array {
			return $this->autowire->autowireClass($className, $methodName, $matchingVariables);
		}
	    
	    /**
	     * Lookup the url and returns information about it
	     * @param Request $request
	     * @return Response
	     */
        public function handle(Request $request): Response {
			// Haal informatie op over de url
			$urlData = $this->urlResolver->resolve($request);
			
			if (!$urlData) {
				return new Response('', Response::HTTP_NOT_FOUND);
			}
			
			// Voer de controller method uit
			try {
				// handle before filter
				$controller = $this->getService($urlData["controller"]);
				$annotationReader = $this->getService(AnnotationsReader::class);
				$annotations = $annotationReader->getClassAnnotations($controller);
				
				foreach ($annotations as $controllerAnnotation) {
					if ($controllerAnnotation instanceof BeforeFilter) {
						$middleware = $this->getService("Services\\Middleware\\{$controllerAnnotation->getName()}");
						$middleware->onBeforeFilter($request, $controller);
					}
				}
				
				// call the controller
				$response = $controller->{$urlData["method"]}(... $this->autowireClass(get_class($controller), $urlData["method"], $urlData["variables"]));
				
				// handle after filter
				foreach ($annotations as $controllerAnnotation) {
					if ($controllerAnnotation instanceof AfterFilter) {
						$middleware = $this->getService("Services\\Middleware\\{$controllerAnnotation->getName()}");
						$middleware->onAfterFilter($request, $controller);
					}
				}

				return $response;
			} catch (\Exception $e) {
				return new Response($e->getMessage(), $e->getCode());
			}
        }
    }