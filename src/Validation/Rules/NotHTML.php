<?php
	
	namespace Quellabs\Canvas\Validation\Rules;
	
	use Quellabs\Canvas\Validation\ValidationRuleInterface;
	
	class NotHTML implements ValidationRuleInterface {
		
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
		
		/**
		 * Validate method
		 * @param mixed $value The value to validate
		 * @return bool Returns true if the value is empty or does not contain any HTML tags, false otherwise
		 */
		public function validate($value): bool {
			if (($value === null) || ($value === "")) {
				return true;
			}
			
			// Normaliseer de invoer door extra spaties te verwijderen
			$normalizedValue = trim(preg_replace('/\s+/', ' ', $value));
			
			// Vervang enkele html entities
			$normalizedValue = str_replace("&amp;", "&", $normalizedValue);
			$normalizedValue = str_replace("&lt;", "<", $normalizedValue);
			$normalizedValue = str_replace("&gt;", ">", $normalizedValue);
			
			// Controleer of er HTML-tags voorkomen in de genormaliseerde string
			return !preg_match('/<[^<]+?>/', $normalizedValue);
		}
		
		/**
		 * Get the error message.
		 * This method retrieves the error message associated with the current object.
		 * If the message is not set, a default error message is returned.
		 * @return string The error message.
		 */
		public function getError(): string {
			if (!isset($this->conditions["message"])) {
				return "{{ key }}: This value contains illegal html tags.";
			}
			
			return $this->conditions["message"];
		}
	}