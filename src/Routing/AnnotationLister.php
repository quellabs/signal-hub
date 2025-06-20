<?php
	
	namespace Quellabs\Canvas\Routing;
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\AnnotationReader\Configuration;
	use Quellabs\Canvas\Annotations\Route;
	use Quellabs\Canvas\AOP\AspectResolver;
	use Quellabs\Discover\Discover;
	use Quellabs\Sculpt\ConfigurationManager;
	
	class AnnotationLister {
		
		/**
		 * Annotation reader class
		 * @var AnnotationReader
		 */
		private AnnotationReader $annotationsReader;
		
		/**
		 * AnnotationLister constructor
		 */
		public function __construct() {
			$annotationsReaderConfig = new Configuration();
			$this->annotationsReader = new AnnotationReader($annotationsReaderConfig);
		}
		
		/**
		 * Discovers and builds a complete list of all routes in the application
		 * by scanning controller classes and their annotated methods
		 * @return array Array of route configurations with controller, method, route, and aspects info
		 */
		public function getRoutes(ConfigurationManager $config): array {
			// Initialize the class discovery utility
			$discover = new Discover();
			
			// Scan the Controller directory to find all controller classes
			// This assumes controllers are located in /src/Controller relative to project root
			$controllers = $discover->findClassesInDirectory($discover->getProjectRoot() . "/src/Controllers");
			
			// Iterate through each discovered controller class
			$result = [];
			
			foreach ($controllers as $controller) {
				// Create a reflection object to inspect the controller class structure
				$classReflection = new \ReflectionClass($controller);
				
				// Examine each method in the current controller
				foreach ($classReflection->getMethods() as $method) {
					// Look for Route annotations on this method
					// Only methods with Route annotations are considered route handlers
					$routes = $this->annotationsReader->getMethodAnnotations(
						$method->getDeclaringClass()->getName(),
						$method->getName(),
						Route::class
					);
					
					// A single method can have multiple Route annotations (multiple routes to same handler)
					foreach ($routes as $route) {
						// Build complete route configuration including metadata
						$result[] = [
							'http_methods' => $route->getMethods(),
							'controller'   => $controller,                // Controller class name
							'method'       => $method->getName(),         // Method name that handles this route
							'route'        => $route,                     // Route annotation object with path/HTTP method info
							'aspects'      => $this->getAspectsOfMethod(  // Any interceptors/middleware for this method
								$method->getDeclaringClass()->getName(),
								$method->getName()
							),
						];
					}
				}
			}
			
			// Sort routes by route first, controller name second, third by method name
			// This makes the route list more predictable and easier to debug
			usort($result, function ($a, $b) {
				// Primary sort: by route
				$routeComparison = $a['route']->getRoute() <=> $b['route']->getRoute();
				
				if ($routeComparison !== 0) {
					return $routeComparison;
				}
				
				// Secondary sort: by controller
				$controllerComparison = $a['controller'] <=> $b['controller'];
				
				if ($controllerComparison !== 0) {
					return $controllerComparison;
				}
				
				// Tertiary sort: by method
				return $a['method'] <=> $b['method'];
			});
			
			// Filter the routes if needed, then return
			return $this->filterRoutes($result, $config);
		}
		
		/**
		 * Filter routes based on configuration options
		 * @param array $routes Collection of routes to filter
		 * @param ConfigurationManager $config Configuration manager containing filter options
		 * @return array Filtered routes array
		 */
		protected function filterRoutes(array $routes, ConfigurationManager $config): array {
			// Get the controller filter option from configuration
			$controllerFilter = $config->get("controller");
			
			// Apply controller filter if specified
			if ($controllerFilter) {
				// Filter routes by controller name
				$routes = array_filter($routes, function ($route) use ($controllerFilter) {
					return str_contains(strtolower($route['controller']), strtolower($controllerFilter));
				});
			}
			
			// Return the filtered routes (or original routes if no filter applied)
			return $routes;
		}
		
		/**
		 * Retrieves all aspect interceptors (middleware/filters) applied to a specific method
		 * @param string $class The fully qualified class name to inspect
		 * @param string $method The method name to check for aspects
		 * @return array Array of interceptor class names ordered by precedence (class-level first, then method-level)
		 */
		protected function getAspectsOfMethod(string $class, string $method): array {
			// Initialize the aspect discovery utility
			$aspectResolver = new AspectResolver($this->annotationsReader);
			
			// Fetch all annotation classes in order
			$aspectsClass = $aspectResolver->resolve($class, $method);
			
			// Extract the actual interceptor class names
			return array_map(function ($e) { return $e['class']; }, $aspectsClass);
		}
	}