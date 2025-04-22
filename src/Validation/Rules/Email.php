<?php
	
	namespace Quellabs\ObjectQuel\Validation\Rules;
	
	class Email implements Quellabs\ObjectQuel\Validation\ValidationInterface {
		
		protected $conditions;
		
		/**
		 * Email constructor
		 * @param array $conditions
		 */
		public function __construct(array $conditions = []) {
			$this->conditions = $conditions;
		}
		
		/**
		 * Returns the conditions used in this Rule
		 * @return array
		 */
		public function getConditions() : array {
			return $this->conditions;
		}
		
		public function validate($value): bool {
			if (($value === "") || is_null($value)) {
                return true;
            }
            
            return filter_var($value, FILTER_VALIDATE_EMAIL);
		}
		
		public function getError(): string {
			if (!isset($this->conditions["message"])) {
				return "This value is not a valid email address.";
			}
			
			return $this->conditions["message"];
		}
		
	}