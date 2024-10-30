<?php
	
	namespace Services\ObjectQuel;
	
	use Services\EntityManager\EntityManager;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\Ast\AstRetrieve;
	use Services\ObjectQuel\Visitors\QuelToSQLConvertToString;
	use Services\ObjectQuel\Visitors\QuelToSQLFetchEntities;
	
	class QuelToSQL {
		
		private EntityManager $entityManager;
		private EntityStore $entityStore;
		
		/**
		 * QuelToSQL constructor
		 * @param EntityManager $entityManager
		 */
		public function __construct(EntityManager $entityManager) {
			$this->entityManager = $entityManager;
			$this->entityStore = $entityManager->getUnitOfWork()->getEntityStore();
		}
		
		/**
		 * Zoekt naar een range met een specifieke naam in een array van ranges.
		 * @param array $ranges De lijst van ranges om te doorzoeken.
		 * @param string $rangeName De naam van de range die gezocht wordt.
		 * @return AstRange|null De gevonden range of null als deze niet gevonden is.
		 */
		private function findRangeByName(array $ranges, string $rangeName): ?AstRange {
			foreach ($ranges as $range) {
				if ($range->getName() === $rangeName) {
					return $range;
				}
			}
			return null;
		}
		
		/**
		 * Controleert of een range al aanwezig is in de resultatenlijst.
		 * @param AstRange $range De range die gecontroleerd moet worden.
		 * @param array $result De lijst van ranges waarin gezocht wordt.
		 * @return bool Geeft true terug als de range al in de lijst staat, anders false.
		 */
		private function isRangeInResult(AstRange $range, array $result): bool {
			foreach ($result as $existingRange) {
				if ($existingRange->getName() === $range->getName()) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Retrieve a list of fully qualified field names, along with aliases, from a given AST Entity object.
		 * @param AstEntity $astEntity The AST Entity object to analyze.
		 * @return array An array of fully qualified field names with aliases.
		 */
		protected function getFieldNamesFromEntity(AstEntity $astEntity): array {
			// Initialize an empty array to hold the result
			$result = [];
			
			// Get the table alias or range from the AST Entity
			$range = $astEntity->getRange()->getName();
			
			// Get the actual entity name from the AST Entity
			$entity = $astEntity->getName();
			
			// Retrieve the column map for the given entity
			$columnMap = $this->entityStore->getColumnMap($entity);
			
			// Loop through each property and corresponding database column in the column map
			foreach ($columnMap as $property => $dbColumn) {
				// Add the fully qualified field name along with an alias to the result array
				$result[] = "{$range}.{$dbColumn} as {$range}_{$property}";
			}
			
			// Return the array of fully qualified field names with aliases
			return $result;
		}
		
		/**
		 * Retrieve the fully qualified field name based on the given AST Identifier.
		 * @param AstIdentifier $astIdentifier The AST Identifier to analyze.
		 * @return string The fully qualified field name.
		 */
		protected function getFieldNameFromIdentifier(AstIdentifier $astIdentifier): string {
			// Get the table alias or range from the entity
			$range = $astIdentifier->getEntity()->getRange()->getName();
			
			// Get the entity name
			$entity = $astIdentifier->getEntity()->getName();
			
			// Get the property name from the AST Identifier
			$propertyName = $astIdentifier->getPropertyName();
			
			// Retrieve the column map based on the entity
			$columnMap = $this->entityStore->getColumnMap($entity);
			
			// Create and return the fully qualified column name
			return "{$range}.{$columnMap[$propertyName]} as {$range}_{$propertyName}";
		}
		
		/**
		 * Returns the keyword DISTINCT if the query is unique
		 * @param AstRetrieve $retrieve
		 * @return string
		 */
		protected function getUnique(AstRetrieve $retrieve): string {
			return $retrieve->isUnique() ? "DISTINCT " : "";
		}
		
		/**
		 * Retrieve a list of fully qualified field names from the given AST Retrieve object.
		 * @param AstRetrieve $retrieve The AST Retrieve object to analyze.
		 * @return string The requested columns as a string
		 */
		protected function getFieldNames(AstRetrieve $retrieve): string {
			// Initialize an empty array to hold the result
			$result = [];
			
			// Loop through each value in the AST Retrieve object
			foreach ($retrieve->getValues() as $value) {
				$quelToSQLConvertToString = new QuelToSQLConvertToString($this->entityStore, "VALUES");
				$value->accept($quelToSQLConvertToString);
				$sqlResult = $quelToSQLConvertToString->getResult();
				
				if (($value instanceof AstAlias) && (!$value->getExpression() instanceof AstEntity)) {
					$sqlResult .= " as `{$value->getName()}`";
				}
				
				$result[] = $sqlResult;
			}
			
			// Convert to string
			return implode(", ", $result);
		}
		
		/**
		 * Verzamel alle entiteiten die in de retrieve-query worden gebruikt.
		 * @param AstRetrieve $retrieve Het retrieve-object waaruit entiteiten worden gehaald.
		 * @return AstEntity[] De lijst van gebruikte entiteiten.
		 */
		protected function getAllEntitiesUsed(AstRetrieve $retrieve): array {
			// Maak een nieuwe instantie van de visitor om entiteiten te verzamelen
			$retrieveEntitiesVisitor = new QuelToSQLFetchEntities();
			
			// Loop door alle waarden in de retrieve-query en verzamel de entiteiten
			foreach($retrieve->getValues() as $value) {
				$value->accept($retrieveEntitiesVisitor);
			}
			
			// Verzamel de entiteiten uit de voorwaarden van de retrieve-query
			$retrieve->getConditions()->accept($retrieveEntitiesVisitor);
			
			// Retourneer de verzamelde entiteiten
			return $retrieveEntitiesVisitor->getEntities();
		}
		
		/**
		 * Verzamelt en retourneert ranges die gebruikt worden in gegeven entities.
		 * Deze functie werkt recursief om join properties van ranges te verwerken.
		 * @param array $ranges De lijst van beschikbare ranges.
		 * @param array $entitiesUsed De lijst van entities om te verwerken.
		 * @return array De lijst van unieke ranges die gebruikt worden in de gegeven entities.
		 */
		protected function getRangesUsedInEntities(array $ranges, array $entitiesUsed): array {
			$result = [];
			
			foreach ($entitiesUsed as $entity) {
				$rangeName = $entity->getRange()->getName();
				
				// Vind de range die overeenkomt met de huidige entity
				$range = $this->findRangeByName($ranges, $rangeName);

				// Voeg de range toe aan resultaten als het nog niet aanwezig is, en verwerk eventuele join properties
				if ($range !== null && !$this->isRangeInResult($range, $result)) {
					$result[] = $range;
				}
			}
			
			return $result;
		}
		
		/**
		 * Genereer het FROM-gedeelte van de SQL-query op basis van ranges zonder JOINS.
		 * @param AstRetrieve $retrieve Het retrieve-object waaruit entiteiten worden gehaald.
		 * @return string Het FROM-gedeelte van de SQL-query.
		 */
		protected function getFrom(AstRetrieve $retrieve): string {
			// Verkrijg alle gebruikte entiteiten in de retrieve-query.
			// Dit omvat het identificeren van de tabellen en hun aliassen voor gebruik in de query.
			$ranges = $retrieve->getRanges();
			
			// Haal alle entiteit-namen op die in de FROM-clausule moeten komen,
			// maar zonder de entiteiten die via JOINs verbonden worden.
			$tableNames = [];
			
			// Doorloop alle ranges (entiteiten) in de retrieve-query.
			foreach($ranges as $range) {
				// Sla ranges met JOIN-eigenschappen over. Deze komen in de JOIN.
				if ($range->getJoinProperty() !== null) {
					continue;
				}
				
				// Verkrijg de naam van de range
				$rangeName = $range->getName();
	
				// Verkrijg de corresponderende tabelnaam voor de entiteit.
				$owningTable = $this->entityStore->getOwningTable($range->getEntity()->getName());
				
				// Voeg de tabelnaam en alias toe aan de lijst voor de FROM-clausule.
				$tableNames[] = "`{$owningTable}` as `{$rangeName}`";
			}
			
			// Retourneer niets als er geen tabellen gerefereerd worden
			if (empty($tableNames)) {
				return "";
			}
			
			// Combineer de tabelnamen met komma's om het FROM-gedeelte van de SQL-query te genereren.
			return " FROM " . implode(",", $tableNames);
		}
		
		/**
		 * Genereer het WHERE-gedeelte van de SQL-query voor de gegeven retrieve-operatie.
		 * Deze functie verwerkt de voorwaarden van de retrieve en zet deze om in een SQL-conforme WHERE-clausule.
		 * @param AstRetrieve $retrieve Het retrieve-object waaruit voorwaarden worden gehaald.
		 * @return string Het WHERE-gedeelte van de SQL-query. Retourneert een lege string als er geen voorwaarden zijn.
		 */
		protected function getWhere(AstRetrieve $retrieve): string {
			// Verkrijg de voorwaarden van de retrieve-operatie.
			$conditions = $retrieve->getConditions();
			
			// Controleer of er voorwaarden zijn. Zo niet, retourneer dan een lege string.
			if ($conditions === null) {
				return "";
			}
			
			// Maak een nieuwe instantie van QuelToSQLConvertToString om de voorwaarden om te zetten naar een SQL-string.
			// Dit object zal de Quel-voorwaarden verwerken en omzetten in een formaat dat SQL begrijpt.
			$retrieveEntitiesVisitor = new QuelToSQLConvertToString($this->entityStore, "WHERE");
			
			// Gebruik de accept-methode van de voorwaarden om het QuelToSQLConvertToString-object de verwerking te laten uitvoeren.
			// Hierdoor wordt de logica voor het omzetten van Quel naar SQL geactiveerd.
			$conditions->accept($retrieveEntitiesVisitor);
			
			// Haal het resultaat op, dat nu een SQL-conforme string is, en voeg 'WHERE' toe voor de SQL-query.
			// Dit is het resultaat van de conversie van Quel-voorwaarden naar SQL.
			return "WHERE " . $retrieveEntitiesVisitor->getResult();
		}
		
		/**
		 * Genereer het ORDER BY-gedeelte van de SQL-query voor de gegeven retrieve-operatie.
		 * Deze functie verwerkt de voorwaarden van de retrieve en zet deze om in een SQL-conforme ORDER BY-clausule.
		 * @param AstRetrieve $retrieve
		 * @return string
		 */
		protected function getSort(AstRetrieve $retrieve): string {
			// Verkrijg de voorwaarden van de retrieve-operatie.
			$sort = $retrieve->getSort();
			
			// Controleer of er voorwaarden zijn. Zo niet, retourneer dan een lege string.
			if (empty($sort)) {
				return "";
			}
			
			// Zet de sort elementen om naar SQL
            $sqlSort = [];
            
            foreach($sort as $s) {
                // Maak een nieuwe instantie van QuelToSQLConvertToString om de voorwaarden om te zetten naar een SQL-string.
                // Dit object zal de Quel-voorwaarden verwerken en omzetten in een formaat dat SQL begrijpt.
                $retrieveEntitiesVisitor = new QuelToSQLConvertToString($this->entityStore, "SORT");

                // Loods de QUEL erdoor om een SQL-query terug te krijgen
                $s['ast']->accept($retrieveEntitiesVisitor);
            
                // Bewaar het queryresultaat
                $sqlSort[] = $retrieveEntitiesVisitor->getResult() . " " . $s["order"];
            }
			
			// Haal het resultaat op, dat nu een SQL-conforme string is, en voeg 'WHERE' toe voor de SQL-query.
			// Dit is het resultaat van de conversie van Quel-voorwaarden naar SQL.
			return " ORDER BY " . implode(",", $sqlSort);
		}
		
		/**
		 * Genereer het JOIN-gedeelte van de SQL-query voor de gegeven retrieve-operatie.
		 * Deze functie analyseert alle entiteiten met join-eigenschappen en converteert deze
		 * naar SQL JOIN-instructies.
		 * @param AstRetrieve $retrieve Het retrieve-object waaruit entiteiten en hun join-eigenschappen worden gehaald.
		 * @return string Het JOIN-gedeelte van de SQL-query, geformatteerd als een string.
		 */
		protected function getJoins(AstRetrieve $retrieve): string {
			$result = [];

			// Haal de lijst van entiteiten op die betrokken zijn bij de retrieve-operatie.
			$ranges = $retrieve->getRanges();
			
			// Doorloop alle entiteiten (ranges) en verwerk degenen met join-eigenschappen.
			foreach($ranges as $range) {
				// Als de entiteit geen join-eigenschap heeft, sla deze dan over.
				if ($range->getJoinProperty() === null) {
					continue;
				}
				
				// Verkrijg de naam en join-eigenschap van de entiteit.
				$rangeName = $range->getName();
				$joinProperty = $range->getJoinProperty();
				$entityName = $range->getEntity()->getName();

				// Vind de tabel die bij de entiteit hoort.
				$owningTable = $this->entityStore->getOwningTable($entityName);
				
				// Zet de join-voorwaarde om naar een SQL-string.
				// Dit houdt in dat de join-voorwaarde wordt vertaald naar een formaat dat SQL begrijpt.
				$visitor = new QuelToSQLConvertToString($this->entityStore, "CONDITION");
				$joinProperty->accept($visitor);
				$joinColumn = $visitor->getResult();
				$joinType = $range->isRequired() ? "INNER" : "LEFT";
				
				// Voeg de SQL JOIN-instructie toe aan het resultaat.
				// Dit resulteert in een LEFT JOIN-instructie voor de betreffende entiteit.
				$result[] = "{$joinType} JOIN `{$owningTable}` as `{$rangeName}` ON {$joinColumn}";
			}
			
			// Converteer de lijst van JOIN-instructies naar een enkele string.
			// Elke JOIN-instructie wordt op een nieuwe regel geplaatst voor betere leesbaarheid.
			return implode("\n", $result);
		}
		
		/**
		 * Convert a retrieve statement to SQL
		 * @param AstRetrieve $retrieve
		 * @return string
		 */
		public function convertToSQL(AstRetrieve $retrieve): string {
			return sprintf("SELECT %s%s%s %s %s%s",
				$this->getUnique($retrieve),
				$this->getFieldNames($retrieve),
				$this->getFrom($retrieve),
				$this->getJoins($retrieve),
				$this->getWhere($retrieve),
				$this->getSort($retrieve)
			);
		}
	}