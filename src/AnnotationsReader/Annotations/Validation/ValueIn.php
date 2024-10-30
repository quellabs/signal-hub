<?php
	
	namespace Services\AnnotationsReader\Annotations\Validation;
	
	class ValueIn {
		
		protected array $parameters;
		
		/**
		 * ValueIn constructor.
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
		 * Returns the values to check
		 * @return array|null
		 */
		public function getValues(): ?array {
			return $this->parameters['values'] ?? null;
		}
		
		/**
		 * Returns the message when the validation fails, null if there's no message configured
		 * @return string|null
		 */
		public function getMessage(): ?string {
			return $this->parameters['message'] ?? null;
		}
	}