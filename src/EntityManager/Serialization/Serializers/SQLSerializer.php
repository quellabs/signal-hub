<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Serialization\Serializers;
	
	use Quellabs\ObjectQuel\EntityManager\Core\EntityStore;
	
	class SQLSerializer extends Serializer {
		
		/**
		 * SQLSerializer constructor
		 * Initialiseert de benodigde handlers en readers.
		 * @param EntityStore $entityStore
		 */
		public function __construct(EntityStore $entityStore) {
			parent::__construct($entityStore);
		}

		/**
		 * Extraheert alle waarden uit de entiteit die gemarkeerd zijn als Column.
		 * @param object $entity De entiteit waaruit de waarden geëxtraheerd moeten worden.
		 * @return array Een array met property namen als keys en hun waarden.
		 */
		public function serialize(object $entity): array {
			// Serializeer de data
			$serializedData = parent::serialize($entity);
			
			// Retrieve the column map (property > database column)
			$columnMap = $this->entityStore->getColumnMap($entity);
			
			// Return updates data
			return array_combine(
				array_values($columnMap),
				array_values($serializedData),
			);
		}
		
		/**
		 * Injecteert de gegeven waarden in de entiteit.
		 * @param object $entity De entiteit waarin de waarden geïnjecteerd moeten worden.
		 * @param array $values De te injecteren waarden, met property namen als keys.
		 * @return void
		 */
		public function deserialize(object $entity, array $values): void {
			// Retrieve the column map (property > database column)
			$columnMap = $this->entityStore->getColumnMap($entity);
			
			// Stap 1: Creëer een tijdelijke array met kolomnamen als zowel key als value
			// Dit is nodig omdat array_intersect_key() werkt met array keys
			$tempColumnMap = array_combine(
				array_values($columnMap),
				array_values($columnMap)
			);
			
			// Stap 2: Filter de keys die zowel in $columnMap als in $values voorkomen
			// array_intersect_key() behoudt alleen de keys uit $tempColumnMap die ook in $values bestaan
			$filteredKeys = array_intersect_key($tempColumnMap, $values);
			
			// Stap 3: Maak de uiteindelijke result array
			// De keys van $filteredKeys zijn nu de property names die we willen gebruiken
			// We mappen elke key naar zijn corresponderende waarde in $values
			$result = array_combine(
				array_keys($filteredKeys),
				array_map(
					fn($key) => $values[$key],
					array_keys($filteredKeys)
				)
			);
			
			// Gebruik de parent methode om te deserialiseren met de gefilterde en getransformeerde data
			parent::deserialize($entity, $result);
		}
	}