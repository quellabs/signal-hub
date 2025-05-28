<?php
	
	namespace Quellabs\Canvas\Routing;
	
	use Quellabs\AnnotationReader\Exception\ParserException;
	use Quellabs\Canvas\Kernel;
	use ReflectionException;
	use Quellabs\Canvas\Annotations\Route;
	use Symfony\Component\HttpFoundation\Request;
	
	class AnnotationResolver {
		
		/**
		 * @var Kernel Kernel object, used among other things for service discovery
		 */
		protected Kernel $kernel;
		
		/**
		 * FetchAnnotations constructor.
		 * @param Kernel $kernel
		 */
		public function __construct(Kernel $kernel) {
			$this->kernel = $kernel;
		}
		
		/**
		 * Resolves an HTTP request to a controller, method, and route variables
		 * Matches the request URL against controller route annotations to find the correct endpoint
		 * @param Request $request The incoming HTTP request to resolve
		 * @return array|null Returns matched route info or null if no match found
		 *         array contains: ['controller' => string, 'method' => string, 'variables' => array]
		 */
		public function resolve(Request $request): ?array {
			// Get the request URL and method
			$baseUrl = ltrim($request->getBaseUrl(), '/');
			$requestUrl = explode('/', $baseUrl);
			
			// Get controller classes from the standard location
			$controllerDir = $this->getControllerDirectory();
			$controllers = $this->kernel->getDiscover()->findClassesInDirectory($controllerDir);
			
			// Find matching route among all controllers
			foreach ($controllers as $controller) {
				$matchedRoute = $this->findMatchingRouteInController(
					$controller,
					$requestUrl,
					$request->getMethod()
				);
				
				if ($matchedRoute) {
					return $matchedRoute;
				}
			}
			
			return null;
		}
		
		/**
		 * Gets the absolute path to the controllers directory
		 * @return string Absolute path to controllers directory
		 */
		protected function getControllerDirectory(): string {
			// Fetch the directory to the controllers
			$directory = !empty(getenv('CONTROLLERS_DIRECTORY')) ? getenv('CONTROLLERS_DIRECTORY') : dirname(__FILE__) . "/../Controller";
			
			// Return an empty string when the user didn't specify CONTROLLERS_DIRECTORY
			if (empty($directory)) {
				return "";
			}
			
			// If it's already an absolute path, normalize and check it
			if (is_dir($directory)) {
				return realpath($directory);
			}
			
			// Otherwise, treat it as a relative path from project root
			$projectRoot = $this->kernel->getDiscover()->getProjectRoot();
			
			// Construct the full path
			$fullPath = $projectRoot . DIRECTORY_SEPARATOR . $directory;
			
			// Make sure the directory exists
			if (!is_dir($fullPath)) {
				return "";
			}
			
			// Return the full path
			return realpath($fullPath);
		}
		
		/**
		 * Finds a matching route in a specific controller
		 * @param string $controller The controller class to check
		 * @param array $requestUrl The parsed request URL segments
		 * @param string $requestMethod The HTTP method of the request
		 * @return array|null The matched route data or null
		 */
		protected function findMatchingRouteInController(string $controller, array $requestUrl, string $requestMethod): ?array {
			try {
				// Get all method annotations that contain route information for this controller
				$routeAnnotations = $this->getMethodRouteAnnotations($controller);
				
				// Iterate through each controller method with route annotations
				foreach ($routeAnnotations as $method => $routeAnnotation) {
					// Split the route pattern into segments for comparison
					$route = explode('/', ltrim($routeAnnotation->getRoute(), '/'));
					
					// Skip if URL segments count doesn't match or HTTP method is wrong
					if (
						count($route) !== count($requestUrl) || // Routes must have same number of segments
						!in_array($requestMethod, $routeAnnotation->getMethods()) // HTTP method must be allowed
					) {
						continue; // Move to the next route if this one can't match
					}
					
					// Container for route parameters extracted from URL
					$urlVariables = [];
					
					// Check if URL segments match the route pattern, collecting any variables
					if ($this->urlMatchesRoute($requestUrl, $route, $urlVariables)) {
						return [
							'controller' => $controller,  // Which controller handles this route
							'method'     => $method,      // Which method to call in the controller
							'variables'  => $urlVariables // Path parameters extracted from URL
						];
					}
				}
			} catch (\Exception $e) {
				// Log exception for debugging (currently commented out)
				// $this->logger->debug("Error processing controller $controller: " . $e->getMessage());
			}
			
			// Return null if no matching route found in this controller
			return null;
		}
		
		/**
		 * Determines if a URL matches a route pattern and extracts any variables
		 * @param array $requestUrl The parsed segments of the requested URL
		 * @param array $routePattern The segments of the route pattern to match against
		 * @param array &$variables Reference to array where extracted variables will be stored
		 * @return bool True if the URL matches the route pattern, false otherwise
		 */
		protected function urlMatchesRoute(array $requestUrl, array $routePattern, array &$variables): bool {
			// Build a new array that will match $requestUrl if the route matches
			$urlCombined = [];
			
			// Loop through each segment of the route pattern
			for ($i = 0; $i < count($routePattern); $i++) {
				$routeSegment = $routePattern[$i];
				$urlSegment = $requestUrl[$i];
				
				if (!empty($routeSegment) && $routeSegment[0] === '{') {
					// This is a variable segment (e.g., {id}, {slug})
					// Extract the variable name by removing the curly braces
					$variableName = trim($routeSegment, '{}');
					
					// Store the actual URL value in our variable array
					$variables[$variableName] = $urlSegment;
					
					// For comparison purposes, use the actual URL segment value
					$urlCombined[] = $urlSegment;
				} else {
					// This is a static segment - must match exactly
					// For comparison purposes, use the route pattern segment
					$urlCombined[] = $routeSegment;
				}
			}
			
			// The route matches if our constructed array matches the original request URL
			// This comparison will fail if static segments don't match exactly
			return $requestUrl === $urlCombined;
		}

		/**
		 * Returns all methods with route annotation in the class
		 * @param string|object $controller The controller class name or object instance to analyze
		 * @return array Associative array where keys are method names and values are Route annotation objects
		 *               Returns an empty array if the controller class doesn't exist or has no route annotations
		 */
		protected function getMethodRouteAnnotations($controller): array {
			try {
				// Create a reflection object to analyze the controller class structure
				$reflectionClass = new \ReflectionClass($controller);
				
				// Get all methods defined in the controller class
				$methods = $reflectionClass->getMethods();
				
				// Initialize a result array to store method name => Route annotation pairs
				$result = [];
				
				// Iterate through each method to find Route annotations
				foreach ($methods as $method) {
					try {
						// Retrieve all annotations for current method
						$annotations = $this->kernel->getAnnotationsReader()->getMethodAnnotations($controller, $method->getName());
						
						// Check each annotation to find Route instances
						foreach ($annotations as $annotation) {
							// If a Route annotation is found, add it to results and skip to next method
							if ($annotation instanceof Route) {
								$result[$method->getName()] = $annotation;
								
								// Skip to the next method after finding a Route annotation (only one Route per method)
								continue 2;
							}
						}
					} catch (ParserException $e) {
						// Silently ignore parser exceptions for individual methods.
						// This allows processing to continue even if one method has invalid annotations
					}
				}
				
				return $result;
			} catch (ReflectionException $e) {
				// Return an empty array if the controller class doesn't exist or can't be reflected
				return [];
			}
		}
	}