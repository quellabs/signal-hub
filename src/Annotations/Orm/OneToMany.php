<?php
	
	namespace Quellabs\ObjectQuel\Annotations\Orm;
	
	use Quellabs\AnnotationReader\AnnotationInterface;
	
	/**
	 * Class OneToMany
	 * Deze klasse representeert een OneToMany-relatie in de ORM en bevat verschillende methodes
	 * om informatie over de relatie te verkrijgen.
	 */
	class OneToMany implements AnnotationInterface {
		
		/**
		 * @var array De parameters die zijn doorgegeven bij de annotatie.
		 */
		protected array $parameters;
		
		/**
		 * OneToMany constructor.
		 * @param array $parameters De parameters van de OneToMany annotatie.
		 */
		public function __construct(array $parameters) {
			$this->parameters = $parameters;
		}
		
		/**
		 * Returns the parameters for this annotation
		 * @return array
		 */
		public function getParameters(): array {
			return $this->parameters;
		}
		
		/**
		 * Haal het target entity op.
		 * @return string De volledige namespace van het target entity.
		 */
		public function getTargetEntity(): string {
			return "Quellabs\\ObjectQuel\\Entity\\{$this->parameters["targetEntity"]}";
		}
		
		/**
		 * Haal de 'mappedBy' parameter op.
		 * @return string|null De waarde van de 'mappedBy' parameter of een lege string als deze niet is ingesteld.
		 */
		public function getMappedBy(): ?string {
			return $this->parameters["mappedBy"] ?? null;
		}
		
		/**
		 * Haal de naam van de relatie-kolom op.
		 * Deze methode haalt de naam van de kolom op die de OneToMany relatie in de database vertegenwoordigt.
		 * De naam van de kolom wordt bepaald op basis van de volgende prioriteiten:
		 * 1. Als de parameter "relationColumn" is ingesteld in de annotatie, dan wordt deze waarde gebruikt.
		 * 2. Als "relationColumn" niet is ingesteld maar "mappedBy" wel, dan wordt de waarde van "mappedBy" gebruikt.
		 * 3. Als geen van beide parameters is ingesteld, wordt null geretourneerd.
		 * @return string|null De naam van de join-kolom of null als deze niet is ingesteld.
		 */
		public function getRelationColumn(): ?string {
			return $this->parameters["relationColumn"] ?? null;
		}
		
		/**
		 * Returns fetch method (default LAZY)
		 * @return string
		 */
		public function getFetch(): string {
			return isset($this->parameters["fetch"]) ? strtoupper($this->parameters["fetch"]) : "LAZY";
		}

		/**
		 * Returns the sort order
		 * @return string
		 */
		public function getOrderBy(): string {
			return $this->parameters["orderBy"] ?? '';
		}
	}