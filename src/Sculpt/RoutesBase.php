<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\AnnotationReader\Configuration;
	use Quellabs\Canvas\Annotations\Route;
	use Quellabs\Canvas\AOP\AspectResolver;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	use Quellabs\Discover\Discover;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	use Quellabs\Sculpt\Contracts\CommandBase;
	
	abstract class RoutesBase extends CommandBase {
		
		/**
		 * AnnotationReader class
		 * @var AnnotationReader
		 */
		protected AnnotationReader $annotationsReader;
		
		/**
		 * RoutesBase constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 * @param ProviderInterface|null $provider
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?ProviderInterface $provider = null) {
			parent::__construct($input, $output, $provider);
			
			// Config for AnnotationsReader
			$annotationsReaderConfig = new Configuration();
			$this->annotationsReader = new AnnotationReader($annotationsReaderConfig);
		}
		
		/**
		 * Discovers and builds a complete list of all routes in the application
		 * by scanning controller classes and their annotated methods
		 * @return array Array of route configurations with controller, method, route, and aspects info
		 */
		protected function getRoutes(): array {
			// Initialize the class discovery utility
			$discover = new Discover();
			
			// Scan the Controller directory to find all controller classes
			// This assumes controllers are located in /src/Controller relative to project root
			$controllers = $discover->findClassesInDirectory($discover->getProjectRoot() . "/src/Controllers");
			
			// Iterate through each discovered controller class
			$result = [];
			
			foreach ($controllers as $controller) {
				// Create reflection object to inspect the controller class structure
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
			
			return $result;
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