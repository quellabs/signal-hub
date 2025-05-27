<?php
	
	namespace Quellabs\Canvas\AOP;
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\AnnotationReader\Exception\ParserException;
	use Quellabs\Canvas\AOP\Contracts\AfterAspect;
	use Quellabs\Canvas\AOP\Contracts\AroundAspect;
	use Quellabs\Canvas\AOP\Contracts\AspectAnnotation;
	use Quellabs\Canvas\AOP\Contracts\BeforeAspect;
	use Quellabs\Canvas\AOP\Contracts\RequestAspect;
	use Quellabs\DependencyInjection\Container;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;
	
	class AspectDispatcher {
		
		/**
		 * AspectDispatcher constructor
		 * @param AnnotationReader $annotationReader
		 * @param Container $di
		 */
		public function __construct(
			private readonly AnnotationReader $annotationReader,
			private readonly Container $di
		) {}
		
		/**
		 * Dispatch a call
		 * @param Request $request
		 * @param object $controller
		 * @param string $method
		 * @param array $parameters
		 * @return Response
		 * @throws ParserException
		 * @throws \ReflectionException
		 */
		public function dispatch(Request $request, object $controller, string $method, array $parameters): Response {
			// Create method context
			$context = new MethodContext(
				target: $controller,
				methodName: $method,
				arguments: $parameters,
				reflection: new \ReflectionMethod($controller, $method),
				annotations: $this->annotationReader->getMethodAnnotations($controller, $method),
				request: $request
			);
			
			// Get and instantiate aspects
			$aspects = $this->resolveAspects($controller, $method);
			
			// Execute request aspects
			foreach ($aspects as $aspect) {
				if ($aspect instanceof RequestAspect) {
					$request = $aspect->transformRequest($request);
				}
			}
			
			// Execute before aspects
			foreach ($aspects as $aspect) {
				if ($aspect instanceof BeforeAspect) {
					$response = $aspect->before($context);
					
					if ($response !== null) {
						return $response;
					}
				}
			}
			
			// Execute method with around aspects
			$result = $this->executeWithAroundAspects($controller, $method, $parameters, $aspects, $context);
			
			// Execute after aspects
			foreach ($aspects as $aspect) {
				if ($aspect instanceof AfterAspect) {
					$response = $aspect->after($context, $result);
					
					if ($response !== null) {
						return $response;
					}
				}
			}
			
			return $result;
		}
		
		/**
		 * Resolves all aspects that should be applied to a controller method
		 * Combines class-level aspects (applied to all methods) with method-level aspects
		 * @param object $controller The controller instance
		 * @param string $method The method name being called
		 * @return array Array of aspect annotation instances
		 */
		private function resolveAspects(object $controller, string $method): array {
			// Get all annotations from the controller class and filter for aspect annotations
			// Class-level aspects apply to all methods in the controller
			$classAnnotations = array_filter($this->annotationReader->getClassAnnotations($controller), function ($e) {
				return $e instanceof AspectAnnotation;
			});
			
			// Get all annotations from the specific method and filter for aspect annotations
			// Method-level aspects only apply to this specific method
			$methodAnnotations = array_filter($this->annotationReader->getMethodAnnotations($controller, $method), function ($e) {
				return $e instanceof AspectAnnotation;
			});
			
			// Merge class-level and method-level aspects
			// Class aspects are applied first, then method aspects
			// This allows method-level aspects to override or extend class-level behavior
			$allAnnotations = array_merge($classAnnotations, $methodAnnotations);
			
			// Convert annotation instances to actual aspect objects with their parameters
			$aspects = [];
			
			foreach ($allAnnotations as $annotation) {
				// Extract the aspect class name from the 'value' parameter
				// This comes from @InterceptWith(CacheAspect::class, ttl=300)
				$aspectClass = $annotation->getInterceptClass(); // e.g., CacheAspect::class
				
				// Get all annotation parameters except 'value' to pass to aspect constructor
				// For @InterceptWith(CacheAspect::class, ttl=300), this gives us ['ttl' => 300]
				$parameters = array_filter($annotation->getParameters(), function ($key) {
					return $key !== 'value';
				}, ARRAY_FILTER_USE_KEY);
				
				// Instantiate the aspect class through DI container
				// DI will autowire dependencies while using annotation parameters for constructor
				// Example: new CacheAspect(cacheService: $injectedCache, ttl: 300)
				$aspects[] = $this->di->get($aspectClass, $parameters);
			}
			
			return $aspects;
		}
		
		/**
		 * Executes the controller method wrapped by around aspects in a nested chain
		 * Around aspects can intercept, modify, or completely replace method execution
		 * @param object $controller The controller instance
		 * @param string $method The method name to execute
		 * @param array $parameters Method parameters
		 * @param array $aspects All resolved aspects for this method
		 * @param MethodContext $context Context information about the method call
		 * @return mixed The result from the method or final around aspect
		 */
		private function executeWithAroundAspects(object $controller, string $method, array $parameters, array $aspects, MethodContext $context): mixed {
			// Filter to get only around aspects from all resolved aspects
			$aroundAspects = array_filter($aspects, fn($aspect) => $aspect instanceof AroundAspect);
			
			// If no around aspects exist, execute the method directly without interception
			if (empty($aroundAspects)) {
				return $this->di->invoke($controller, $method, $parameters);
			}
			
			// Create the base "proceed" function that calls the actual controller method
			// This is the innermost function in the chain
			$proceed = fn() => $this->di->invoke($controller, $method, $parameters);
			
			// Build nested chain of around aspects in reverse order
			// Reverse order ensures first declared aspect becomes outermost wrapper
			// Each aspect wraps the previous proceed function, creating nested calls
			foreach (array_reverse($aroundAspects) as $aspect) {
				$currentProceed = $proceed; // Capture current proceed function in closure
				$proceed = fn() => $aspect->around($context, $currentProceed); // Wrap with this aspect
			}
			
			// Execute the complete chain starting from the outermost aspect
			return $proceed();
		}
	}