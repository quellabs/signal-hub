<?php
	
	namespace Services\AnnotationsReader\Annotations\Validation;
	
	class Type {
		
		protected array $parameters;
		
		/**
		 * Type constructor.
		 * @param array $parameters
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Returns all parameters
		 * @return array
		 */
		public function getParameters(): array {
			return $this->parameters;
		}

		/**
		 * Returns the type to check
		 * @return string|null
		 */
		public function getType(): ?string {
			return $this->parameters['type'] ?? null;
		}
		
		/**
		 * Returns the message when the validation fails, null if there's no message configured
		 * @return string|null
		 */
		public function getMessage(): ?string {
			return $this->parameters['message'] ?? null;
		}
	}