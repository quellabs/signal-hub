<?php
	
	namespace Quellabs\Canvas\AOP\Contracts;
	
	use Quellabs\Canvas\AOP\MethodContext;
	use Symfony\Component\HttpFoundation\Response;
	
	/**
	 * Interface for aspect-oriented programming "after" advice.
	 */
	interface AfterAspect extends AspectAnnotation {
		
		/**
		 * Executes after the target method has completed.
		 *
		 * This method is called automatically by the aspect system after
		 * the intercepted method finishes execution. It receives both the
		 * method context and the result of the original method call.
		 *
		 * @param MethodContext $context Contains metadata about the intercepted method
		 * @param mixed $result The return value from the original method execution.
		 *                      It can be null if the method doesn't return anything.
		 *
		 * @return Response|null Optional HTTP response to override the default behavior.
		 */
		public function after(MethodContext $context, mixed $result): ?Response;
	}