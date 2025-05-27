<?php
	
	namespace Quellabs\Canvas\AOP\Contracts;
	
	use Symfony\Component\HttpFoundation\Request;
	
	/**
	 * Interface for implementing request-based aspects in the AOP framework.
	 */
	interface RequestAspect extends AspectAnnotation {
		
		/**
		 * Executed before the request is processed by the routing system.
		 * @param Request $request The incoming HTTP request object
		 * @return Request Returns the modified request object
		 */
		public function transformRequest(Request $request): Request;
	}