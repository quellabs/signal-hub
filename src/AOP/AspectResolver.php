<?php
	
	namespace Quellabs\Canvas\AOP;
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\Canvas\Annotations\InterceptWith;
	
	readonly class AspectResolver {
		
		/**
		 * AnnotationReader is used to read annotations in docblocks
		 * @var AnnotationReader
		 */
		private AnnotationReader $annotationReader;
		
		/**
		 * AspectDispatcher constructor
		 * @param AnnotationReader $annotationReader
		 */
		public function __construct(AnnotationReader $annotationReader) {
			$this->annotationReader = $annotationReader;
		}
		
		/**
		 * Resolves all aspects that should be applied to a controller method
		 * Combines class-level aspects (applied to all methods) with method-level aspects
		 * @param object|string $controller The controller instance
		 * @param string $method The method name being called
		 * @return array Array of aspect annotation instances
		 */
		public function resolve(object|string $controller, string $method): array {
			// Get all annotations from the controller class and filter for aspect annotations
			// Class-level aspects apply to all methods in the controller
			$classAnnotations = array_filter($this->annotationReader->getClassAnnotations($controller), function ($e) {
				return $e instanceof InterceptWith;
			});
			
			// Get all annotations from the specific method and filter for aspect annotations
			// Method-level aspects only apply to this specific method
			$methodAnnotations = array_filter($this->annotationReader->getMethodAnnotations($controller, $method), function ($e) {
				return $e instanceof InterceptWith;
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
				
				// Add the aspect class and parameters to the result list
				$aspects[] = [
					'class'      => $aspectClass,
					'parameters' => $parameters
				];
			}
			
			return $aspects;
		}
	}