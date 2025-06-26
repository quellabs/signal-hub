<?php
	
	namespace Quellabs\Canvas\Validation;
	
	/**
	 * Interface for validation rule providers
	 *
	 * This interface defines the contract for classes that provide
	 * validation rules. Implementing classes should return an array
	 * of validation rules that can be used by the validation system.
	 */
	interface ValidationInterface {
		
		/**
		 * Returns an array of validation rules.
		 * @return array An array of validation rules
		 */
		public function getRules(): array;
	}