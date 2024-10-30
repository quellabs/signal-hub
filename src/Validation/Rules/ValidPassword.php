<?php
	
	namespace Services\Validation\Rules;
	
	class ValidPassword implements \Services\Validation\ValidationInterface {
		
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
            if ($value == "") {
                return true;
            }
            
            // must contain lowercase character
			if (!preg_match('~[a-z]~', $value)) {
                return false;
            }
			
            // must contain uppercase character
			if (!preg_match('~[A-Z]~', $value)) {
                return false;
            }
			
            // must contain number
			if (!preg_match('~\d~', $value)) {
                return false;
            }
			
			return true;
		}
		
		public function getError(): string {
			if (!isset($this->conditions["message"])) {
				return "This value does not meet the criteria for a valid password.";
			}
			
			return $this->conditions["message"];
		}
		
	}