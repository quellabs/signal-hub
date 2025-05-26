<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\AnnotationReader\Configuration;
	use Quellabs\Canvas\Annotations\InterceptWith;
	use Quellabs\Canvas\Annotations\Route;
	use Quellabs\Canvas\AOP\Contracts\AspectAnnotation;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	use Quellabs\Discover\Discover;
	use Quellabs\Sculpt\CommandBase;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	
	class ListRoutesCommand extends CommandBase {
		
		private AnnotationReader $annotationsReader;
		
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?ProviderInterface $provider = null) {
			parent::__construct($input, $output, $provider);

			// Config for AnnotationsReader
			$annotationsReaderConfig = new Configuration();
			$this->annotationsReader = new AnnotationReader($annotationsReaderConfig);
		}
		
		public function getSignature(): string {
			return "route:list";
		}
		
		public function getDescription(): string {
			return "List routes";
		}
		
		/**
		 * List the routes
		 * @param ConfigurationManager $config
		 * @return int
		 */
		public function execute(ConfigurationManager $config): int {
			// Get all registered routes from the application
			$routes = $this->getRoutes();
			
			// Transform route data into table format for display
			$tableData = array_map(function (array $entry) {
				return [
					// Format route path with leading slash
					"/" . $entry['route']->getRoute(),
					
					// Format controller as ClassName@methodName
					$entry['controller'] . "@" . $entry['method'],
					
					// Format aspects as comma-separated list in brackets
					"[" . implode(",", $entry['aspects']) . "]",
				];
			}, $routes);
			
			// Display routes in a formatted table with headers
			$this->getOutput()->table(['Route', 'Controller', 'Aspects'], $tableData);
			
			// Return success status
			return 0;
		}
		
		/**
		 * Retrieves all aspect interceptors (middleware/filters) applied to a specific method
		 * @param string $class The fully qualified class name to inspect
		 * @param string $method The method name to check for aspects
		 * @return array Array of interceptor class names ordered by precedence (class-level first, then method-level)
		 */
		private function getAspectsOfMethod(string $class, string $method): array {
			// Collect class-level interceptors that apply to all methods in this controller
			// These are typically used for controller-wide concerns like authentication or CORS
			$aspectsClass = array_filter($this->annotationsReader->getClassAnnotations($class), function($annotation) {
				return $annotation instanceof InterceptWith;
			});
			
			// Collect method-specific interceptors that apply only to this particular route handler
			// These are used for method-specific concerns like input validation or caching
			$aspectsMethod = array_filter($this->annotationsReader->getMethodAnnotations($class, $method), function($annotation) {
				return $annotation instanceof InterceptWith;
			});
			
			// Merge interceptors with class-level aspects first (executed first in the chain)
			// then method-level aspects (executed closer to the actual method call)
			// Extract the actual interceptor class names from the InterceptWith annotation objects
			return array_map(function($e) {
				return $e->getInterceptClass();
			}, array_merge($aspectsClass, $aspectsMethod));
		}
		
		/**
		 * Discovers and builds a complete list of all routes in the application
		 * by scanning controller classes and their annotated methods
		 * @return array Array of route configurations with controller, method, route, and aspects info
		 */
		private function getRoutes(): array {
			// Initialize the class discovery utility
			$discover = new Discover();
			
			// Scan the Controller directory to find all controller classes
			// This assumes controllers are located in /src/Controller relative to project root
			$controllers = $discover->findClassesInDirectory($discover->getProjectRoot() . "/src/Controller");
			
			// Initialize array to store all discovered route configurations
			$result = [];
			
			// Iterate through each discovered controller class
			foreach($controllers as $controller) {
				// Create reflection object to inspect the controller class structure
				$classReflection = new \ReflectionClass($controller);
				
				// Examine each method in the current controller
				foreach($classReflection->getMethods() as $method) {
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
							'controller' => $controller,               // Controller class name
							'method'     => $method->getName(),        // Method name that handles this route
							'route'      => $route,                    // Route annotation object with path/HTTP method info
							'aspects'   => $this->getAspectsOfMethod(  // Any interceptors/middleware for this method
								$method->getDeclaringClass()->getName(),
								$method->getName()
							),
						];
					}
				}
			}
			
			// Sort routes alphabetically by controller name for consistent ordering
			// This makes the route list more predictable and easier to debug
			usort($result, function($a, $b) {
				return strcmp($a['controller'], $b['controller']);
			});
			
			return $result;
		}
	}