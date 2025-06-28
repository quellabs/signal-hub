<?php
	namespace Quellabs\Contracts\AOP;
	
	use Symfony\Component\HttpFoundation\Request;
	
	/**
	 * Interface for implementing request-based aspects in the AOP framework.
	 */
	interface RequestAspect extends AspectAnnotation {
		
		/**
		 * Transform the request before it's processed by business logic.
		 * Modify the request object directly - no return value needed.
		 * @param Request $request The incoming HTTP request object to modify
		 * @return void
		 */
		public function transformRequest(Request $request): void;
	}