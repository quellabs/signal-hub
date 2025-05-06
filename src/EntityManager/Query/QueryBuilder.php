<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Query;
	
	use Quellabs\ObjectQuel\EntityManager\EntityStore;
	
	class QueryBuilder {
		
		private EntityStore $entityStore;
		
		/**
		 * QueryBuilder constructor
		 * @param EntityStore $entityStore
		 */
		public function __construct(EntityStore $entityStore) {
			$this->entityStore = $entityStore;
		}
		
		/**
		 * Genereert een array van bereikdefinities voor de hoofdentiteit en haar relaties.
		 * Deze methode wordt gebruikt om de bereiken te definiëren voor de query die uitgevoerd zal worden.
		 * Het combineert zowel ManyToOne als OneToMany afhankelijkheden om een uitgebreid overzicht
		 * van de relaties van de entiteit te bieden. Als er geen ManyToOne afhankelijkheden zijn,
		 * wordt de hoofdentiteit als een stand-alone bereik toegevoegd.
		 * @param string $entityType De entiteitstype waarvoor de relaties opgehaald moeten worden.
		 * @return array Een array met de bereikdefinities voor de entiteit en haar relaties.
		 */
		private function getRelationRanges(string $entityType): array {
			$ranges = [];
			$rangeCounter = 0;
			
			// Eerst range is altijd de main
			$ranges['main'] = "range of main is {$entityType}";
            
			// Zoek op welke entities een relatie hebben met deze entity en verwerk ze
			foreach($this->entityStore->getDependentEntities($entityType) as $dependentEntityType) {
				$this->processOneToOneDependencies($entityType, $dependentEntityType, $ranges, $rangeCounter);
				
				// Verwerkt ManyToOne relaties en voegt deze toe aan de bereiken.
				$this->processManyToOneDependencies($entityType, $dependentEntityType, $ranges, $rangeCounter);
			}
			
			// Retourneer de ranges lijst
			return $ranges;
		}
		
		/**
		 * Convert an associative array to a string representation
		 * This method converts an associative array to a string representation where the keys and values are
		 * concatenated with the provided prefix and separated by the string "=". The resulting key-value pairs
		 * are then joined together using the string " AND".
		 * @param array<string, mixed> $parameters The associative array to be converted
		 * @param string $prefix The prefix to be applied to each key in the array
		 * @return string The converted string representation of the array
		 */
		private function parametersToString(array $parameters, string $prefix): string {
			$resultParts = [];
			
			foreach ($parameters as $key => $value) {
				$resultParts[] = "{$prefix}.{$key}=:{$key}";
			}
			
			return implode(" AND ", $resultParts);
		}
		
		/**
		 * Creëert een unieke alias voor een range op basis van een meegegeven teller.
		 * Deze methode genereert een alias door de huidige waarde van de range teller
		 * voor te stellen met een 'r' prefix. Deze alias wordt gebruikt om ranges uniek te
		 * identificeren binnen een query.
		 * @param int $rangeCounter Een referentie naar de teller die wordt gebruikt om een unieke alias te genereren.
		 * @return string De gegenereerde unieke alias voor de range.
		 */
		private function createAlias(int &$rangeCounter): string {
			return "r{$rangeCounter}";
		}
		
		/**
		 * Verwerkt OneToOne afhankelijkheden voor een gegeven entity type.
		 * @param string $entityType Het type van de entiteit waarvoor relaties worden verwerkt.
		 * @param string $dependentEntityType
		 * @param array $ranges Een array van bestaande ranges die moet worden uitgebreid.
		 * @param int $rangeCounter Een teller om unieke aliassen voor ranges te creëren.
		 * @return void
		 */
		private function processOneToOneDependencies(string $entityType, string $dependentEntityType, array &$ranges, int &$rangeCounter): void {
			// Haal alle niet LAZY manyToOne dependencies op
			$oneToOneDependencies = $this->entityStore->getOneToOneDependencies($dependentEntityType);
			$oneToOneDependenciesFiltered = array_filter($oneToOneDependencies, function($e) { return $e->getInversedBy() === null; });
			$oneToOneDependenciesFiltered = array_filter($oneToOneDependenciesFiltered, function($e) { return $e->getFetch() !== "LAZY"; });
			$oneToOneDependenciesFiltered = array_filter($oneToOneDependenciesFiltered, function($e) use ($entityType) { return $e->getTargetEntity() === $entityType; });
			
			foreach ($oneToOneDependenciesFiltered as $propertyName => $relation) {
				// Creëer een unieke alias voor de range.
				$alias = $this->createAlias($rangeCounter);
				
				// Haal relatie kolommen op
				$inversedBy = $relation->getInversedBy();
				$relationColumn = $relation->getRelationColumn();
				
				// Voeg de range toe
				$ranges[$alias] = "range of {$alias} is {$dependentEntityType} via {$alias}.{$relationColumn}=main.{$inversedBy}";
				
				// Verhoog de range counter voor de volgende unieke range.
				++$rangeCounter;
			}
		}
        
        /**
		 * Verwerkt ManyToOne afhankelijkheden voor een gegeven entity type.
		 * Deze methode loopt door alle ManyToOne relaties van het opgegeven entiteitstype en voegt
		 * voor elke relatie een nieuwe range toe aan de meegegeven array. Deze ranges worden
		 * gebruikt voor het bouwen van query's met gerelateerde entiteiten. Tevens wordt de hoofdentiteit
		 * ingesteld met een 'via' clausule indien er ManyToOne relaties bestaan.
		 * @param string $entityType Het type van de entiteit waarvoor relaties worden verwerkt.
		 * @param string $dependentEntityType
		 * @param array $ranges Een array van bestaande ranges die moet worden uitgebreid.
		 * @param int $rangeCounter Een teller om unieke aliassen voor ranges te creëren.
		 * @return void
		 */
		private function processManyToOneDependencies(string $entityType, string $dependentEntityType, array &$ranges, int &$rangeCounter): void {
			// Haal alle niet LAZY manyToOne dependencies op
			$manyToOneDependencies = $this->entityStore->getManyToOneDependencies($dependentEntityType);
			$manyToOneDependenciesFiltered = array_filter($manyToOneDependencies, function($e) { return $e->getFetch() !== "LAZY"; });
			$manyToOneDependenciesFiltered = array_filter($manyToOneDependenciesFiltered, function($e) use ($entityType) { return $e->getTargetEntity() === $entityType; });
			
			foreach ($manyToOneDependenciesFiltered as $propertyName => $relation) {
				// Creëer een unieke alias voor de range.
				$alias = $this->createAlias($rangeCounter);
				
				// Haal relatie kolommen op
				$inversedBy = $relation->getInversedBy();
				$relationColumn = $relation->getRelationColumn();
				
				// Voeg de nieuwe range toe aan de lijst.
				$ranges[$alias] = "range of {$alias} is {$dependentEntityType} via {$alias}.{$relationColumn}=main.{$inversedBy}";
				
				// Verhoog de range counter voor de volgende unieke range.
				++$rangeCounter;
			}
		}
        
		/**
		 * Bereidt een query voor op basis van het gegeven entiteitstype en primaire sleutels.
		 * Deze functie genereert een query string die gebruikt kan worden om een entiteit en
		 * haar gerelateerde entiteiten op te halen.
		 * @param string $entityType Het type van de entiteit waarvoor de query wordt voorbereid.
		 * @param array $primaryKeys De primaire sleutels voor de entiteit.
		 * @return string De samengestelde query string.
		 */
		public function prepareQuery(string $entityType, array $primaryKeys): string {
			// Haal de bereikdefinities op voor de relaties van de entiteit.
			$relationRanges = $this->getRelationRanges($entityType);
			
			// Implementeer de bereikdefinities in de query.
			$rangesImpl = implode("\n", $relationRanges);
			
			// Maak een WHERE-string op basis van de primaire sleutels.
			$whereString = $this->parametersToString($primaryKeys, "main");
			
			// Combineer alles tot de uiteindelijke query string.
			return "{$rangesImpl}\nretrieve unique (" . implode(",", array_keys($relationRanges)) . ") where {$whereString}";
		}
	}