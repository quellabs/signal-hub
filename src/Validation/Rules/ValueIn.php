<?php
	
	namespace Quellabs\Canvas\Validation\Rules;
	
	use Quellabs\Canvas\Validation\ValidationRuleInterface;
	
	class ValueIn implements ValidationRuleInterface {
		
		protected mixed $conditions;
		
		/**
		 * ValueIn constructor
		 * @param array $conditions
		 */
		public function __construct(array $conditions = []) {
			$this->conditions = $conditions["values"] ?? [];
		}
		
		/**
		 * Returns the conditions used in this Rule
		 * @return array
		 */
		public function getConditions() : array {
			return $this->conditions;
		}
		
		/**
		 * Perform the validation
		 * @param $value
		 * @return bool
		 */
		public function validate($value): bool {
			if (empty($this->conditions["values"]) || ($value == "") || ($value == null)) {
				return true;
			}
			
			return in_array($value, $this->conditions);
		}
		
		/**
		 * Returns error when validation fails
		 * @return string
		 */
		public function getError(): string {
			if (!isset($this->conditions["message"])) {
				return "Value should be any of these: " . implode(",", array_map(function($e) { return "'{$e}'"; }, $this->conditions["values"]));
			}

			return $this->conditions["message"];
		}
	}