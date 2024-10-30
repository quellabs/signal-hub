<?php
	
	namespace Services\AnnotationsReader\Annotations\Orm;
	
	/**
	 * De klasse QuelEquivalent is bedoeld om Quel-equivalente waarden te hanteren.
	 */
	class QuelEquivalent {
		
		// Beschermd variabele die de parameters zal bevatten.
		protected array $parameters;
		
		/**
		 * Constructor voor QuelEquivalent.
		 * Deze methode initialiseert de klasse met de meegegeven parameters.
		 * @param array $parameters
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Deze methode retourneert de Quel-equivalente waarde.
		 * @return string De Quel-equivalente waarde.
		 */
		public function getSqlEquivalent(): string {
			return $this->parameters["value"];
		}
	}