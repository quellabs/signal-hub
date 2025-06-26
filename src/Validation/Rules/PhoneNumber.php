<?php
	
	namespace Quellabs\Canvas\Validation\Rules;
	
	use Quellabs\Canvas\Validation\ValidationRuleInterface;
	
	class PhoneNumber implements ValidationRuleInterface {
		
		protected array $conditions;
		
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

            // Allow digits, spaces, commas, periods, hyphens, and the plus sign
            return strcmp(preg_replace('/[^0-9\s,.\-+]/', '', $value), $value) == 0;
        }

        public function getError(): string {
			if (!isset($this->conditions["message"])) {
				return "This value does not meet the criteria for a valid phone number.";
			}
			
			return $this->conditions["message"];
		}
	}