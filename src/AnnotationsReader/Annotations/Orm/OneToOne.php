<?php
	
	namespace Services\AnnotationsReader\Annotations\Orm;
	
	/**
	 * Definieert de OneToOne klasse die de relatie tussen entiteiten beschrijft
	 */
	class OneToOne {
		
		// Bevat parameters die extra informatie over de relatie geven
		protected array $parameters;
		
		/**
		 * Constructor om de parameters te initialiseren.
		 * @param array $parameters Array met parameters die de relatie beschrijven.
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Haalt de target entiteit op.
		 * @return string De volledige namespace van de doelentiteit.
		 */
		public function getTargetEntity(): string {
			return "Services\\Entity\\{$this->parameters["targetEntity"]}";
		}
		
		/**
		 * Haal de 'mappedBy' parameter op.
		 * @return string De waarde van de 'mappedBy' parameter of een lege string als deze niet is ingesteld.
		 */
		public function getMappedBy(): ?string {
			return $this->parameters["mappedBy"] ?? null;
		}
		
		/**
		 * Haalt de 'inversedBy' parameter op, als die aanwezig is.
		 * @return string|null De naam van het veld in de doelentiteit dat naar de huidige entiteit verwijst, of null als het niet is ingesteld.
		 */
		public function getInversedBy(): ?string {
			return $this->parameters["inversedBy"] ?? null;
		}
		
		/**
		 * Haal de naam van de relatie-kolom op.
		 * Deze methode haalt de naam van de kolom op die de ManyToOne relatie in de database vertegenwoordigt.
		 * De naam van de kolom wordt bepaald op basis van de volgende prioriteiten:
		 * 1. Als de parameter "relationColumn" is ingesteld in de annotatie, dan wordt deze waarde gebruikt.
		 * 2. Als "relationColumn" niet is ingesteld maar "inversedBy" wel, dan wordt de waarde van "inversedBy" gebruikt.
		 * 3. Als geen van beide parameters is ingesteld, wordt null geretourneerd.
		 * @return string|null De naam van de join-kolom of null als deze niet is ingesteld.
		 */
		public function getRelationColumn(): ?string {
			if (!isset($this->parameters["relationColumn"])) {
				return null;
			}
			
			return $this->parameters["relationColumn"];
		}
		
		/**
		 * Returns fetch method (default LAZY)
		 * @return string
		 */
		public function getFetch(): string {
			if (empty($this->parameters["fetch"])) {
				return "LAZY";
			}
			
			return strtoupper($this->parameters["fetch"]);
		}
	}