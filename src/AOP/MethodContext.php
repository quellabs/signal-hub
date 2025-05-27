<?php
	
	namespace Quellabs\Canvas\AOP;
	
	use Symfony\Component\HttpFoundation\Request;
	
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
		private array $annotations;
		
		/**
		 * Initialize the method context with all relevant call information.
		 * @param object $target The original object instance on which the method is being called
		 * @param string $methodName Name of the method being invoked
		 * @param array $arguments Array of arguments passed to the method
		 * @param \ReflectionMethod $reflection Reflection object for accessing method metadata
		 * @param array $annotations Parsed annotations/attributes associated with the method
		 */
		public function __construct(
			Request $request,              // The request object
			object $target,                // The original object instance
			string $methodName,            // Method being called
			array $arguments,              // Method parameters
			\ReflectionMethod $reflection, // For metadata
			array $annotations = []        // Parsed annotations
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
			return $this->arguments;
		}
		
		/**
		 * Get a specific argument by its position index.
		 * @param int $index Zero-based index of the argument
		 * @return mixed The argument value, or null if index doesn't exist
		 */
		public function getArgument(int $index): mixed {
			return $this->arguments[$index] ?? null;
		}
		
		/**
		 * Modify an argument at a specific position.
		 * Useful for interceptors that need to transform method parameters.
		 * @param int $index Zero-based index of the argument to modify
		 * @param mixed $value New value for the argument
		 */
		public function setArgument(int $index, mixed $value): void {
			$this->arguments[$index] = $value;
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
					'type'          => $parameter->getType()->getName(),
					'default_value' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null
				];
			}
			
			return $result;
		}
	}