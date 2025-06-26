<?php
	
	namespace Quellabs\Canvas\Validation\Rules;
	
	use Quellabs\Canvas\Validation\ValidationRuleInterface;
	
	/**
	 * Class Email
	 * Implementatie van een validatieregel voor e-mailadressen
	 */
	class Email implements ValidationRuleInterface {
		
		/**
		 * Voorwaarden voor de validatie
		 */
		protected array $conditions;
		
		/**
		 * Constructor van de Email class
		 * @param array $conditions Voorwaarden voor de validatie
		 */
		public function __construct(array $conditions = []) {
			$this->conditions = $conditions;
		}
		
		/**
		 * Haalt de voorwaarden op die gebruikt worden in deze regel
		 * @return array De voorwaarden voor de validatie
		 */
		public function getConditions() : array {
			return $this->conditions;
		}
		
		/**
		 * Valideert of de waarde een geldig e-mailadres is
		 * @param mixed $value De waarde die gevalideerd moet worden
		 * @return bool True als de waarde een geldig e-mailadres is, anders false
		 */
		public function validate(mixed $value): bool {
			// Als de waarde een lege string is of null, wordt het als geldig beschouwd
			if (($value === "") || is_null($value)) {
				return true;
			}
			
			// Controleer of de waarde een geldig e-mailadres is
			return filter_var($value, FILTER_VALIDATE_EMAIL);
		}
		
		/**
		 * Haalt de foutmelding op als de waarde niet geldig is
		 * @return string De foutmelding
		 */
		public function getError(): string {
			// Als er geen aangepaste foutmelding is ingesteld, gebruik de standaardmelding
			if (!isset($this->conditions["message"])) {
				return "This value is not a valid email address.";
			}
			
			// Retourneer de aangepaste foutmelding
			return $this->conditions["message"];
		}
	}