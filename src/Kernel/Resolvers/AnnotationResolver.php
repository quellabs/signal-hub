<?php
	
	namespace Quellabs\ObjectQuel\Kernel\Resolvers;
	
	use Quellabs\ObjectQuel\AnnotationsReader\Annotations\Route;
	use Quellabs\ObjectQuel\AnnotationsReader\AnnotationsReader;
	use Quellabs\ObjectQuel\Kernel\Kernel;
	use Quellabs\ObjectQuel\Kernel\ServiceLocator;
	use Quellabs\ObjectQuel\Validation\AnnotationsToValidation;
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
		 * Returns all validation annotations
		 * @param string $controller
		 * @param string $method
		 * @return array
		 */
		protected function getMethodValidationAnnotations(string $controller, string $method): array {
			$result = [];
			$annotationReader = $this->kernel->getService(AnnotationsReader::class);
			$annotations = $annotationReader->getMethodAnnotations($controller, $method);
			
			foreach ($annotations as $annotation) {
				$className = get_class($annotation);
				$namespace = substr($className, 0, strrpos($className, '\\'));
				
				if (str_starts_with($namespace, "Services\AnnotationsReader\Annotations\Validation")) {
					$result[] = $annotation;
				}
			}
			
			return $result;
		}
	
		/**
		 * Resolves a HTTP request to a controller, method and route variables
		 * Matches the request URL against controller route annotations to find the correct endpoint
		 *
		 * @param Request $request The incoming HTTP request to resolve
		 * @return array{controller: string, method: string, variables: array} Route information
		 */
		public function resolve(Request $request): array {
			// Initialize required services and paths
			$annotationsToValidation = $this->kernel->getService(AnnotationsToValidation::class);
			$controllerDir = realpath(dirname(__FILE__) . '/../../Controller');
			$controllers = $this->dirToArray($controllerDir);
			
			// Parse request path
			//$requestPath = explode('/', ltrim($request->query->get('query'), '/'));
			$requestPath = explode("/", "hallo/10");
			$requestMethod = $request->getMethod();
			
			// Search through controllers for matching route
			foreach ($controllers as $controllerFile) {
				try {
					// Convert the file path to a namespace
					$controllerClass = str_replace(DIRECTORY_SEPARATOR, '\\', $controllerFile);
					$routeAnnotations = $this->getMethodRouteAnnotations($controllerClass);
					
					// Check each method's route annotation
					foreach ($routeAnnotations as $method => $routeAnnotation) {
						$routePath = explode('/', ltrim($routeAnnotation->getRoute(), '/'));
						
						// Skip if path segments don't match or wrong HTTP method
						if (count($routePath) !== count($requestPath) ||
							!in_array($requestMethod, $routeAnnotation->getMethods())) {
							continue;
						}
						
						// Extract and validate route variables
						$urlCombined = [];
						$variables = [];
						
						for ($i = 0; $i < count($routePath); $i++) {
							if (empty($routePath[$i]) || $routePath[$i][0] !== '{') {
								$urlCombined[] = $routePath[$i];
							} else {
								$urlCombined[] = $requestPath[$i];
								$variables[trim($routePath[$i], '{}')] = $requestPath[$i];
							}
						}
						
						// Check if paths match
						if ($requestPath !== $urlCombined) {
							continue;
						}
						
						// If no variables, return immediately
						if (empty($variables)) {
							return [
								'controller' => $controllerClass,
								'method'     => $method,
								'variables'  => $variables
							];
						}
						
						// Validate route parameters if present
						$validationAnnotations = $this->getMethodValidationAnnotations($controllerClass, $method);
						$validationRules = $annotationsToValidation->convert($validationAnnotations);
						
						if (!empty($validationRules)) {
							foreach ($validationRules as $property => $rule) {
								if (!$rule->validate($variables[$property])) {
									throw new \Exception($rule->getError());
								}
							}
						}
						
						return [
							'controller' => $controllerClass,
							'method'     => $method,
							'variables'  => $variables
						];
					}
				} catch (\Exception $e) {
					continue;
				}
			}
			
			throw new \Exception("No route found for path: {$request->query->get('query')}");
		}
	}