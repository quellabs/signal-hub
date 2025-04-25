<?php
	
	namespace Quellabs\ObjectQuel\Annotations\Orm;
	
	use Quellabs\AnnotationReader\AnnotationInterface;
	
	class PrimaryKeyStrategy implements AnnotationInterface {
		
		protected array $parameters;
		
		/**
		 * PrimaryKeyStrategy constructor.
		 * @param array $parameters
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Returns the parameters for this annotation
		 * @return array
		 */
		public function getParameters(): array {
			return $this->parameters;
		}

		/**
		 * Returns the chosen strategy.
		 * @return mixed
		 */
		public function getValue(): mixed {
			return $this->parameters["strategy"] ?? 'auto_increment';
		}
	}