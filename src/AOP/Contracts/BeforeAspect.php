<?php
	
	namespace Quellabs\Canvas\AOP\Contracts;
	
	use Quellabs\Canvas\AOP\MethodContext;
	use Symfony\Component\HttpFoundation\Response;
	
	/**
	 * Interface for aspect-oriented programming "before" advice.
	 */
	interface BeforeAspect extends AspectAnnotation {
		
		/**
		 * Executes before the target method is called.
		 *
		 * This method is called automatically by the aspect system before
		 * the intercepted method begins execution. It can perform pre-processing,
		 * validation, logging, or security checks. If a Response is returned,
		 * the original method will be bypassed entirely.
		 *
		 * @param MethodContext $context Contains metadata about the method that will be executed
		 * @return Response|null Optional HTTP response to short-circuit execution.
		 *                       Return null to allow the original method to proceed normally.
		 */
		public function before(MethodContext $context): ?Response;
	}