<?php
	
	namespace Services\AnnotationsReader\Annotations\Validation;
	
	class RegExp {
		
		protected array $parameters;
		
		/**
		 * RegExp constructor.
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
		 * Returns the regexp to check
		 * @return string|null
		 */
		public function getRegExp(): ?string {
			return $this->parameters['regexp'] ?? null;
		}
		
		/**
		 * Returns the message when the validation fails, null if there's no message configured
		 * @return string|null
		 */
		public function getMessage(): ?string {
			return $this->parameters['message'] ?? null;
		}
	}