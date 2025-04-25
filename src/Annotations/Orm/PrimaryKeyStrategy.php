<?php
	
	namespace Quellabs\ObjectQuel\Annotations\Orm;
	
	class PrimaryKeyStrategy {
		
		protected array $parameters;
		
		/**
		 * PrimaryKeyStrategy constructor.
		 * @param array $parameters
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Returns the chosen strategy.
		 * @return mixed
		 */
		public function getValue(): mixed {
			return $this->parameters["strategy"] ?? 'auto_increment';
		}
	}