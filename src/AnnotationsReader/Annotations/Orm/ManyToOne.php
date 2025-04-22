<?php
	
	namespace Quellabs\ObjectQuel\AnnotationsReader\Annotations\Orm;
	
	/**
	 * Definieert de ManyToOne klasse die de relatie tussen entiteiten beschrijft
	 */
	class ManyToOne {
		
		// Bevat parameters die extra informatie over de relatie geven
		protected $parameters;
		
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
			return "Quellabs\\ObjectQuel\\Entity\\{$this->parameters["targetEntity"]}";
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
			if (isset($this->parameters["relationColumn"])) {
				return $this->parameters["relationColumn"];
			} elseif (isset($this->parameters["inversedBy"])) {
				return $this->parameters["inversedBy"];
			} else {
				return null;
			}
		}
		
		/**
		 * Returns fetch method (default LAZY)
		 * @return mixed|string
		 */
		public function getFetch(): string {
			return isset($this->parameters["fetch"]) ? strtoupper($this->parameters["fetch"]) : "EAGER";
		}
	}