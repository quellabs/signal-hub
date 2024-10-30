<?php
	
	namespace Services\AnnotationsReader\Annotations\Validation;
	
	class Zipcode {
		
		protected array $parameters;
		
		/**
		 * Zipcode constructor.
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
		 * Returns the iso2 code
		 * @return string|null
		 */
		public function getIso2(): ?string {
			return $this->parameters['iso2'] ?? null;
		}
		
		/**
		 * Returns the message when the validation fails, null if there's no message configured
		 * @return string|null
		 */
		public function getMessage(): ?string {
			return $this->parameters['message'] ?? null;
		}
	}