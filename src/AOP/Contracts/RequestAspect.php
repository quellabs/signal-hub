<?php
	
	namespace Quellabs\Canvas\AOP\Contracts;
	
	use Symfony\Component\HttpFoundation\Request;
	
	/**
	 * Interface for implementing request-based aspects in the AOP framework.
	 *
	 * This interface extends AspectAnnotation to provide aspect-oriented programming
	 * capabilities specifically for HTTP request handling. It allows intercepting
	 * and potentially modifying requests before they reach the routing layer.
	 */
	interface RequestAspect extends AspectAnnotation {
		
		/**
		 * Executed before the request is processed by the routing system.
		 *
		 * This method is called as part of the aspect weaving process and allows
		 * the aspect to inspect, modify, or replace the incoming HTTP request
		 * before it continues through the application pipeline.
		 *
		 * @param Request $request The incoming HTTP request object
		 *
		 * @return Request|null Returns the modified request object to continue processing,
		 *                      or null to halt request processing (e.g., for security reasons)
		 */
		public function beforeRouting(Request $request): ?Request;
	}