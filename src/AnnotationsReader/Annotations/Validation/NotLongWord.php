<?php
	
	namespace Services\AnnotationsReader\Annotations\Validation;
	
	class NotLongWord {
		
		protected array $parameters;
		
		/**
		 * NotLongWord constructor.
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
		 * Returns the minimum length
		 * @return int|null
		 */
		public function getLength(): ?int {
			return $this->parameters['length'] ?? null;
		}
		
		/**
		 * Returns the message when the validation fails, null if there's no message configured
		 * @return string|null
		 */
		public function getMessage(): ?string {
			return $this->parameters['message'] ?? null;
		}
	}