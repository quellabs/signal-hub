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
		
		private AnnotationReader $annotationReader;
		private Container $di;
		
		/**
		 * AspectDispatcher constructor
		 * @param AnnotationReader $annotationReader
		 * @param Container $di
		 */
		public function __construct(AnnotationReader $annotationReader, Container $di) {
			$this->annotationReader = $annotationReader;
			$this->di = $di;
		}
		
		/**
		 * Dispatch a call
		 * @param Request $request
		 * @param object $controller
		 * @param string $method
		 * @param array $arguments
		 * @return Response
		 * @throws ParserException
		 * @throws \ReflectionException
		 */
		public function dispatch(Request $request, object $controller, string $method, array $arguments): Response {
			// Create method context
			$context = new MethodContext(
				request: $request,
				target: $controller,
				methodName: $method,
				arguments: $arguments,
				reflection: new \ReflectionMethod($controller, $method),
				annotations: $this->annotationReader->getMethodAnnotations($controller, $method)
			);
			
			// Get and instantiate aspects
			$aspects = $this->getResolvedAspects($controller, $method);
			
			// Execute request aspects
			foreach ($aspects as $aspect) {
				if ($aspect instanceof RequestAspect) {
					$aspect->transformRequest($request);
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
			$result = $this->executeWithAroundAspects($controller, $method, $aspects, $context);
			
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
		 * Resolves and instantiates aspects for a given controller method.
		 * @param object $controller The controller instance that contains the target method
		 * @param string $method The name of the method to resolve aspects for
		 * @return array Array of instantiated aspect objects ready for execution
		 */
		private function getResolvedAspects(object $controller, string $method): array {
			// Instantiate AspectResolver, which is used to find AOP classes
			$aspectResolver = new AspectResolver($this->annotationReader);
			
			// Use the aspect resolver to determine which aspects apply to this controller method
			// Returns an array where keys are aspect class names and values are constructor parameters
			$aspects = $aspectResolver->resolve($controller, $method);
			
			// Iterate through each resolved aspect and instantiate it
			$aspectsResolved = [];

			foreach($aspects as $aspectClass => $parameters) {
				// Use dependency injection container to create aspect instance with its parameters
				// The DI container handles constructor injection and aspect lifecycle
				$aspectsResolved[] = $this->di->get($aspectClass, $parameters);
			}
			
			return $aspectsResolved;
		}
		
		/**
		 * Executes the controller method wrapped by around aspects in a nested chain
		 * Around aspects can intercept, modify, or completely replace method execution
		 * @param object $controller The controller instance
		 * @param string $method The method name to execute
		 * @param array $aspects All resolved aspects for this method
		 * @param MethodContext $context Context information about the method call
		 * @return mixed The result from the method or final around aspect
		 */
		private function executeWithAroundAspects(object $controller, string $method, array $aspects, MethodContext $context): mixed {
			// Filter to get only around aspects from all resolved aspects
			$aroundAspects = array_filter($aspects, fn($aspect) => $aspect instanceof AroundAspect);
			
			// If no around aspects exist, execute the method directly without interception
			if (empty($aroundAspects)) {
				return $this->di->invoke($controller, $method, $context->getArguments());
			}
			
			// Create the base "proceed" function that calls the actual controller method
			// This is the innermost function in the chain
			$proceed = fn() => $this->di->invoke($controller, $method, $context->getArguments());
			
			// Build nested chain of around aspects in reverse order.
			// Reverse order ensures first declared aspect becomes outermost wrapper.
			// Each aspect wraps the previous proceed function, creating nested calls.
			foreach (array_reverse($aroundAspects) as $aspect) {
				$currentProceed = $proceed; // Capture current proceed function in closure
				$proceed = fn() => $aspect->around($context, $currentProceed); // Wrap with this aspect
			}
			
			// Execute the complete chain starting from the outermost aspect
			return $proceed();
		}
	}