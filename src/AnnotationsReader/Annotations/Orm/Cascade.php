<?php
	
	namespace Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm;
	
	/**
	 * Class Cascade
	 *
	 * Defines cascading behavior for entity relationships
	 * Used to specify what operations should cascade and how they should be implemented
	 *
	 * @package Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm
	 */
	class Cascade {
		
		/**
		 * Contains all parameters defined in the annotation
		 * Example: @Orm\Cascade(operations={"remove"}, strategy="database")
		 */
		protected array $parameters;
		
		/**
		 * Cascade constructor.
		 * @param array $parameters Array of parameters from the annotation
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Get the operations that should cascade
		 *
		 * Possible values include:
		 * - "remove": Cascade deletion
		 * - "persist": Cascade persistence
		 * - "all": Cascade all operations
		 *
		 * @return array List of operations to cascade
		 */
		public function getOperations(): array {
			return $this->parameters['operations'] ?? [];
		}
		
		/**
		 * Get the strategy for implementing cascades
		 *
		 * Possible values:
		 * - "orm": Implement at ORM level only
		 * - "database": Implement using database constraints
		 * - "both": Implement at both levels
		 *
		 * @return string The cascading strategy
		 */
		public function getStrategy(): string {
			return $this->parameters['strategy'] ?? "both";
		}
	}