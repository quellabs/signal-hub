<?php
	
	namespace Services\Validation\Rules;
	
	class NotLongWord implements \Services\Validation\ValidationInterface {
		
		protected $conditions;
		protected $length;
		
		/**
		 * Email constructor
		 * @param array $conditions
		 */
		public function __construct(array $conditions = []) {
			$this->conditions = $conditions;
			
			if (isset($this->conditions["length"]) && is_numeric($this->conditions["length"])) {
				$this->length = $this->conditions["length"];
			} else {
				$this->length = 30;
			}
		}
		
		/**
		 * Returns the conditions used in this Rule
		 * @return array
		 */
		public function getConditions() : array {
			return $this->conditions;
		}
		
		/**
		 * Check if any of the words in the text are longer than the specified length.
		 * If so, return false to signify that the validation failed.
		 * @param $value
		 * @return bool
		 */
		public function validate($value): bool {
			// do not check if value empty
			if (($value === "") || is_null($value)) {
                return true;
            }
			
			// check all words and return false if any of them exceeds the length
			$words = explode(' ', $value);
			
			foreach ($words as $value) {
				if (mb_strlen($value, 'utf8') > $this->length) {
					return false;
				}
			}
			
			return true;
		}
		
		public function getError(): string {
			if (!isset($this->conditions["message"])) {
				return "{{ key }}: One of the words exceeds the length of {$this->length}.";
			}
			
			return $this->conditions["message"];
		}
	}