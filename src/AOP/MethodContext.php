<?php
	
	namespace Quellabs\Canvas\AOP;
	
	use Quellabs\AnnotationReader\Collection\AnnotationCollection;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Session\SessionInterface;
	
	/**
	 * Context object that encapsulates information about a method call.
	 * Used in AOP (Aspect-Oriented Programming) scenarios to provide
	 * interceptors and decorators with complete method execution context.
	 */
	class MethodContext {
		
		private Request $request;
		private object $target;
		private string $methodName;
		private array $arguments;
		private \ReflectionMethod $reflection;
		private AnnotationCollection $annotations;
		
		/**
		 * Initialize the method context with all relevant call information.
		 * @param Request $request
		 * @param object $target The original object instance on which the method is being called
		 * @param string $methodName Name of the method being invoked
		 * @param array $arguments Array of arguments passed to the method
		 * @param \ReflectionMethod $reflection Reflection object for accessing method metadata
		 * @param AnnotationCollection $annotations Parsed annotations/attributes associated with the method
		 */
		public function __construct(
			Request $request,              // The request object
			object $target,                // The original object instance
			string $methodName,            // Method being called
			array $arguments,              // Method parameters
			\ReflectionMethod $reflection, // For metadata
			AnnotationCollection $annotations  // Parsed annotations
		) {
			$this->request = $request;
			$this->target = $target;
			$this->methodName = $methodName;
			$this->arguments = $arguments;
			$this->reflection = $reflection;
			$this->annotations = $annotations;
		}
		
		/**
		 * Get the target object instance.
		 * @return object The original object on which the method is being called
		 */
		public function getTarget(): object {
			return $this->target;
		}
		
		/**
		 * Get the name of the method being called.
		 * @return string The method name
		 */
		public function getMethodName(): string {
			return $this->methodName;
		}
		
		/**
		 * Get all arguments passed to the method.
		 * @return array Array of method arguments in order
		 */
		public function getArguments(): array {
			$arguments = $this->arguments;
			
			foreach($this->getMethodArguments() as $methodArgument) {
				// Skip handling argument without a type
				if ($methodArgument['type'] === null) {
					continue;
				}
				
				// Special case handling for Request and SessionInterface classes
				switch($methodArgument['type']) {
					case Request::class :
						$arguments[$methodArgument['name']] = $this->getRequest();
						break;
						
					case SessionInterface::class :
						$arguments[$methodArgument['name']] = $this->getRequest()->getSession();
						break;
				}
			}
			
			return $arguments;
		}
		
		/**
		 * Get parsed annotations/attributes for the method.
		 * @return array Array of parsed method annotations
		 */
		public function getAnnotations(): array {
			return $this->annotations;
		}
		
		/**
		 * Get the ReflectionMethod object for accessing method metadata.
		 * Provides access to method visibility, parameters, return type, etc.
		 * @return \ReflectionMethod The reflection object for the method
		 */
		public function getReflection(): \ReflectionMethod {
			return $this->reflection;
		}
		
		/**
		 * Returns the request object
		 * @return Request
		 */
		public function getRequest(): Request {
			return $this->request;
		}
		
		/**
		 * Sets the request object
		 * @param Request $request
		 * @return void
		 */
		public function setRequest(Request $request): void {
			$this->request = $request;
		}
		
		/**
		 * Returns the method arguments
		 * @return array
		 */
		public function getMethodArguments(): array {
			$result = [];
			
			foreach($this->getReflection()->getParameters() as $parameter) {
				$result[] = [
					'name'          => $parameter->getName(),
					'type'          => $parameter->getType() ? $parameter->getType()->getName() : null,
					'default_value' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null
				];
			}
			
			return $result;
		}
	}