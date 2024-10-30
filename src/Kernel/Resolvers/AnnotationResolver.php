<?php
    
    namespace Services\Kernel\Resolvers;
    
    use Services\AnnotationsReader\Annotations\Route;
    use Services\AnnotationsReader\AnnotationsReader;
    use Services\Kernel\Kernel;
    use Services\Kernel\ServiceLocator;
    use Symfony\Component\HttpFoundation\Request;
    
    class AnnotationResolver {
	    
	    protected Kernel $kernel;
	    protected ServiceLocator $serviceLocator;
	    
	    /**
	     * FetchAnnotations constructor.
	     * @param Kernel $kernel
	     */
        public function __construct(Kernel $kernel) {
            $this->kernel = $kernel;
            $this->serviceLocator = $kernel->getServiceLocator();
        }
	    
	    /**
	     * Scant een directory recursief en converteert alle PHP bestanden naar class names
	     * met de juiste namespace voor controllers
	     * Voorbeeld: "Controller/User/UserController.php" wordt "Services\Controllers\User\UserController"
	     * @param string $dir Absolute pad naar de te scannen directory
	     * @return array<string> Array met fully qualified class names
	     * @throws \RuntimeException Als de directory niet leesbaar is
	     */
	    protected function dirToArray(string $dir): array {
		    // Controleer of we de directory kunnen lezen
		    if (!is_readable($dir)) {
			    throw new \RuntimeException("Directory not readable: {$dir}");
		    }
		    
		    // Stel de basis namespace in voor controllers
		    $baseNamespace = 'Services\\Controller';
		    
		    // Loop door alle bestanden en directories
		    $result = [];
		    
		    foreach (scandir($dir) ?: [] as $entry) {
			    // Sla specifieke system files over
			    if (in_array($entry, ['.', '..', '.htaccess'], true)) {
				    continue;
			    }
			    
			    $path = $dir . DIRECTORY_SEPARATOR . $entry;
			    
			    if (is_dir($path)) {
				    // Als het een directory is, scan deze recursief
				    $result = [...$result, ...$this->dirToArray($path)];
			    } elseif (str_ends_with($entry, '.php')) {
				    // Haal het relatieve pad op vanaf de Controller directory
				    $relativePath = substr($path, strlen($dir) + 1);
				    
				    // Converteer pad naar namespace formaat
				    $namespace = str_replace(
					    [DIRECTORY_SEPARATOR, '.php'],
					    ['\\', ''],
					    $relativePath
				    );
				    
				    // Voeg de basis namespace toe
				    $result[] = $baseNamespace . '\\' . $namespace;
			    }
		    }
		    
		    return $result;
	    }
		
        /**
         * Returns all methods with route annotation in the class
         * @param $controller
         * @return array
         * @throws \ReflectionException
         */
        protected function getMethodRouteAnnotations($controller): array {
            $result = [];
	        $annotationReader = $this->kernel->getService(AnnotationsReader::class);
            $reflectionClass = new \ReflectionClass($controller);
            $methods = $reflectionClass->getMethods();
        
            foreach ($methods as $method) {
                $annotations = $annotationReader->getMethodAnnotations($controller, $method->getName());
            
                foreach ($annotations as $annotation) {
                    if ($annotation instanceof Route) {
                        $result[$method->getName()] = $annotation;
						continue 2;
                    }
                }
            }
        
            return $result;
        }
	    
	    /**
	     * Resolves a HTTP request to a controller, method and route variables
	     * Matches the request URL against controller route annotations to find the correct endpoint
	     * @param Request $request The incoming HTTP request to resolve
	     * @return array|null Returns matched route info or null if no match found
	     *         array contains: controller class, method name and route variables
	     * @throws \Exception
	     */
	    public function resolve(Request $request): ?array {
		    // Get the absolute path to controllers directory
		    $controllerDir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "Controller");
			
		    // Get the list of controller files
		    $fileList = $this->dirToArray($controllerDir);
		    
		    // Get and normalize request base URL
		    $url = explode("/", ltrim($request->query->get('query'), "/"));
		    
		    // Loop through each controller file
		    foreach($fileList as $fl) {
			    try {
				    // Convert the file path to namespace format
				    $controller = str_replace(DIRECTORY_SEPARATOR, "\\", $fl);
				    
				    // Get route annotations for controller methods
				    $routeAnnotations = $this->getMethodRouteAnnotations($controller);
				    
				    // Check each method's route annotation
				    foreach ($routeAnnotations as $method => $routeAnnotation) {
					    $route = explode("/", ltrim($routeAnnotation->getRoute(), "/"));
					    
					    // Skip if URL segments count doesn't match or HTTP method is wrong
					    if (
							count($route) !== count($url) ||
						    !in_array($request->getMethod(), $routeAnnotation->getMethods())
					    ) {
						    continue;
					    }
					    
					    // Process URL parameters and build comparison URL
					    $urlCombined = [];
					    $urlVariables = [];
					    
					    for ($i = 0; $i < count($route); ++$i) {
						    // Check if the segment is a variable (starts with {)
						    if (empty($route[$i]) || ($route[$i][0] !== "{")) {
							    $urlCombined[] = $route[$i];
						    } else {
							    $urlCombined[] = $url[$i];
							    $urlVariables[trim($route[$i], "{}")] = $url[$i];
						    }
					    }
					    
					    // Early return if URLs match
					    if ($url === $urlCombined) {
						    return [
							    'controller' => $controller,
							    'method'     => $method,
							    'variables'  => $urlVariables
						    ];
					    }
				    }
			    } catch (\Exception $e) {
				    // Skip controller if any error occurs during processing
				    continue;
			    }
		    }
		    
			throw new \Exception("No controller found for {$request->query->get('query')}");
	    }
    }