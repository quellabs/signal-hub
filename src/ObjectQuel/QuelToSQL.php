<?php
	
	namespace Quellabs\ObjectQuel\ObjectQuel;
	
	use Quellabs\ObjectQuel\EntityManager\EntityStore;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstAlias;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstIdentifier;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstRangeDatabase;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstRetrieve;
	use Quellabs\ObjectQuel\ObjectQuel\Visitors\GetMainEntityInAst;
	use Quellabs\ObjectQuel\ObjectQuel\Visitors\GetMainEntityInAstException;
	use Quellabs\ObjectQuel\ObjectQuel\Visitors\QuelToSQLConvertToString;
	use Quellabs\ObjectQuel\ObjectQuel\Visitors\QuelToSQLFetchEntities;
	
	class QuelToSQL {
		
		private EntityStore $entityStore;
		private array $parameters;
		
		/**
		 * QuelToSQL constructor
		 * @param EntityStore $entityStore
		 * @param array $parameters
		 */
		public function __construct(EntityStore $entityStore, array &$parameters) {
			$this->entityStore = $entityStore;
			$this->parameters = &$parameters;
		}
		
		/**
		 * Zoekt naar een range met een specifieke naam in een array van ranges.
		 * @param array $ranges De lijst van ranges om te doorzoeken.
		 * @param string $rangeName De naam van de range die gezocht wordt.
		 * @return AstRangeDatabase|null De gevonden range of null als deze niet gevonden is.
		 */
		private function findRangeByName(array $ranges, string $rangeName): ?AstRangeDatabase {
			foreach ($ranges as $range) {
				if ($range->getName() === $rangeName) {
					return $range;
				}
			}
			
			return null;
		}
		
		/**
		 * Controleert of een range al aanwezig is in de resultatenlijst.
		 * @param string $rangeName De naam van de range die gecontroleerd moet worden.
		 * @param array $result De lijst van ranges waarin gezocht wordt.
		 * @return bool Geeft true terug als de range al in de lijst staat, anders false.
		 */
		private function isRangeNameInResult(string $rangeName, array $result): bool {
			return $this->findRangeByName($result, $rangeName) !== null;
		}
		
		/**
		 * Retrieve a list of fully qualified field names, along with aliases, from a given AST Entity object.
		 * @param AstIdentifier $identifier The AST Entity object to analyze.
		 * @return array An array of fully qualified field names with aliases.
		 */
		protected function getFieldNamesFromEntity(AstIdentifier $identifier): array {
			// Initialize an empty array to hold the result
			$result = [];
			
			// Get the table alias or range from the AST Entity
			$range = $identifier->getRange()->getName();
			
			// Get the actual entity name from the AST Entity
			$entity = $identifier->getEntityName();
			
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
			$range = $astIdentifier->getRange()->getName();
			
			// Get the entity name
			$entity = $astIdentifier->getName();
			
			// Get the property name from the AST Identifier
			$propertyName = $astIdentifier->getNext()->getName();
			
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
		 * Returns true if the identifier is an entity, false if not
		 * @param AstInterface $ast
		 * @return bool
		 */
		protected function identifierIsEntity(AstInterface $ast): bool {
			return (
				$ast instanceof AstIdentifier &&
				$ast->getRange() instanceof AstRangeDatabase &&
				!$ast->hasNext()
			);
		}
		
		/**
		 * Haalt de veldnamen op uit een AstRetrieve object en converteert deze naar een SQL-compatibele string.
		 * @param AstRetrieve $retrieve Het AstRetrieve object om te verwerken.
		 * @return string De geformatteerde veldnamen als een enkele string.
		 */
		protected function getFieldNames(AstRetrieve $retrieve): string {
			// Initialiseer een lege array om het resultaat op te slaan
			$result = [];
			
			// Loop door elke waarde in het AstRetrieve object
			foreach ($retrieve->getValues() as $value) {
				// Maak een nieuwe QuelToSQLConvertToString converter
				$quelToSQLConvertToString = new QuelToSQLConvertToString($this->entityStore, $this->parameters, "VALUES");
				
				// Accepteer de waarde voor conversie
				$value->accept($quelToSQLConvertToString);
				
				// Haal het geconverteerde SQL-resultaat op
				$sqlResult = $quelToSQLConvertToString->getResult();
				
				// Controleer of de alias geen volledige entity is
				if (!empty($sqlResult)) {
					if (($value instanceof AstAlias) && !$this->identifierIsEntity($value->getExpression())) {
						// Voeg de alias toe aan het SQL-resultaat
						$sqlResult .= " as `{$value->getName()}`";
					}
					
					// Voeg het SQL-resultaat toe aan de resultaat array
					$result[] = $sqlResult;
				}
			}
			
			// Converteer de array naar een string en verwijder dubbele waarden
			return implode(",", array_unique($result));
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
				// Sla JSON ranges over
				if (!$range instanceof AstRangeDatabase) {
					continue;
				}
				
				// Sla ranges met JOIN-eigenschappen over. Deze komen in de JOIN.
				if ($range->getJoinProperty() !== null) {
					continue;
				}
				
				// Verkrijg de naam van de range
				$rangeName = $range->getName();
	
				// Verkrijg de corresponderende tabelnaam voor de entiteit.
				$owningTable = $this->entityStore->getOwningTable($range->getEntityName());
				
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
			$retrieveEntitiesVisitor = new QuelToSQLConvertToString($this->entityStore, $this->parameters, "WHERE");
			
			// Gebruik de accept-methode van de voorwaarden om het QuelToSQLConvertToString-object de verwerking te laten uitvoeren.
			// Hierdoor wordt de logica voor het omzetten van Quel naar SQL geactiveerd.
			$conditions->accept($retrieveEntitiesVisitor);
			
			// Haal het resultaat op, dat nu een SQL-conforme string is, en voeg 'WHERE' toe voor de SQL-query.
			// Dit is het resultaat van de conversie van Quel-voorwaarden naar SQL.
			return "WHERE " . $retrieveEntitiesVisitor->getResult();
		}
		
		/**
		 * Directly manipulate the values in IN() without extra queries
		 * @param AstRetrieve $retrieve
		 * @return string
		 */
		private function getSortUsingIn(AstRetrieve $retrieve): string {
			// Controleer en haal de primaire sleutel informatie op
			$primaryKeyInfo = $this->entityStore->fetchPrimaryKeyOfMainRange($retrieve);
			
			if (!is_array($primaryKeyInfo)) {
				return $this->getSortDefault($retrieve);
			}
			
			// Maak een AstIdentifier voor het zoeken naar een IN() in de query
			$astIdentifier = new AstIdentifier($primaryKeyInfo['entityName']);
			
			try {
				$visitor = new GetMainEntityInAst($astIdentifier);
				$retrieve->getConditions()->accept($visitor);
				return $this->getSortDefault($retrieve);
			} catch (GetMainEntityInAstException $exception) {
				$astObject = $exception->getAstObject();
				
				// Converteer Quel-voorwaarden naar een SQL-string
				$retrieveEntitiesVisitor = new QuelToSQLConvertToString($this->entityStore, $this->parameters, "SORT");
				$astObject->getIdentifier()->accept($retrieveEntitiesVisitor);
				
				// Verwerk de resultaten tot een SQL ORDER-BY-clausule
				$parametersSql = implode(",", array_unique(array_map(function ($e) { return $e->getValue(); }, $astObject->getParameters())));
				return " ORDER BY FIELD(" . $retrieveEntitiesVisitor->getResult() . ", " . $parametersSql . ")";
			}
		}
		
		/**
		 * Regular sort handler
		 * @param AstRetrieve $retrieve
		 * @return string
		 */
		protected function getSortDefault(AstRetrieve $retrieve): string {
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
				$retrieveEntitiesVisitor = new QuelToSQLConvertToString($this->entityStore, $this->parameters, "SORT");
				
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
		 * Genereer het ORDER BY-gedeelte van de SQL-query voor de gegeven retrieve-operatie.
		 * Deze functie verwerkt de voorwaarden van de retrieve en zet deze om in een SQL-conforme ORDER BY-clausule.
		 * @param AstRetrieve $retrieve
		 * @return string
		 */
		protected function getSort(AstRetrieve $retrieve): string {
			// Als de compiler directive @InValuesAreFinal meegegeven is, dan moeten we sorteren op de
			// volgorde binnen de IN() lijst
			$compilerDirectives = $retrieve->getDirectives();
			
			if (isset($compilerDirectives['InValuesAreFinal']) && ($compilerDirectives['InValuesAreFinal'] === true)) {
				return $this->getSortUsingIn($retrieve);
			} elseif (!$retrieve->getSortInApplicationLogic()) {
				return $this->getSortDefault($retrieve);
			} else {
				return "";
			}
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
				// Sla de range over als deze een json data-source is
				if (!$range instanceof AstRangeDatabase) {
					continue;
				}
				
				// Als de entiteit geen join-eigenschap heeft, sla deze dan over.
				if ($range->getJoinProperty() === null) {
					continue;
				}
				
				// Verkrijg de naam en join-eigenschap van de entiteit.
				$rangeName = $range->getName();
				$joinProperty = $range->getJoinProperty();
				$entityName = $range->getEntityName();

				// Vind de tabel die bij de entiteit hoort.
				$owningTable = $this->entityStore->getOwningTable($entityName);
				
				// Zet de join-voorwaarde om naar een SQL-string.
				// Dit houdt in dat de join-voorwaarde wordt vertaald naar een formaat dat SQL begrijpt.
				$visitor = new QuelToSQLConvertToString($this->entityStore, $this->parameters, "CONDITION");
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