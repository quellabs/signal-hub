<?php
	
	// Namespace declaration voor gestructureerde code
	namespace Quellabs\ObjectQuel\ObjectQuel\Visitors;
	
	// Importeer de vereiste klassen en interfaces
	use Services\AnnotationsReader\Annotations\Orm\Column;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstBinaryOperator;
	use Services\ObjectQuel\Ast\AstBool;
	use Services\ObjectQuel\Ast\AstConcat;
	use Services\ObjectQuel\Ast\AstCount;
   use Quellabs\ObjectQuel\ObjectQuel\Ast\AstCheckNull;
   use Quellabs\ObjectQuel\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstIn;
	use Services\ObjectQuel\Ast\AstIsEmpty;
	use Services\ObjectQuel\Ast\AstIsFloat;
	use Services\ObjectQuel\Ast\AstIsInteger;
	use Services\ObjectQuel\Ast\AstIsNumeric;
	use Services\ObjectQuel\Ast\AstNot;
	use Services\ObjectQuel\Ast\AstNull;
	use Services\ObjectQuel\Ast\AstNumber;
	use Services\ObjectQuel\Ast\AstParameter;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\Ast\AstRangeJsonSource;
	use Services\ObjectQuel\Ast\AstRegExp;
	use Services\ObjectQuel\Ast\AstSearch;
	use Services\ObjectQuel\Ast\AstString;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\Ast\AstUCount;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
   use Quellabs\ObjectQuel\ObjectQuel\Ast\AstCheckNotNull;
    
    /**
	 * Class QuelToSQLConvertToString
	 * Implementeert AstVisitor om entiteiten uit een AST te verzamelen.
	 */
	class QuelToSQLConvertToString implements AstVisitorInterface {
		
		// De entity store voor entity naar table conversies
		private EntityStore $entityStore;
		
		// Array om verzamelde entiteiten op te slaan
		private array $result;
		private array $visitedNodes;
		private array $parameters;
		private string $partOfQuery;
		
		/**
		 * Constructor om de entities array te initialiseren.
		 * @param EntityStore $store
		 * @param array $parameters
		 * @param string $partOfQuery
		 */
		public function __construct(EntityStore $store, array &$parameters, string $partOfQuery="VALUES") {
			$this->result = [];
			$this->visitedNodes = [];
			$this->entityStore = $store;
			$this->parameters = &$parameters;
			$this->partOfQuery = $partOfQuery;
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
		 * Determines the return type of the identifier by checking its annotations
		 * @param AstIdentifier $identifier Entity identifier to analyze
		 * @return string|null Column type if found in annotations, null otherwise
		 */
		private function inferReturnTypeOfIdentifier(AstIdentifier $identifier): ?string {
			// Get all annotations for the entity
			$annotationList = $this->entityStore->getAnnotations($identifier->getEntityName());
			
			// Check if identifier has annotations
			if (!isset($annotationList[$identifier->getName()])) {
				return null;
			}
			
			// Search for Column annotation to get type
			foreach ($annotationList[$identifier->getName()] as $annotation) {
				if ($annotation instanceof Column) {
					return match ($annotation->getType()) {
						'varchar' => 'string',
						'int' => 'integer',
						'decimal' => 'float',
						default => $annotation->getType()
					};
				}
			}
			
			return null;
		}
		
		/**
		 * Recursively infers the return type of an AST node and its children
		 * @param AstInterface $ast Abstract syntax tree node
		 * @return string|null Inferred return type or null if none found
		 */
		public function inferReturnType(AstInterface $ast): ?string {
			// Boolean operations
			if ($ast instanceof AstBinaryOperator || $ast instanceof AstExpression) {
				return 'boolean';
			}
			
			// Process identifiers
			if ($ast instanceof AstIdentifier) {
				return $this->inferReturnTypeOfIdentifier($ast);
			}
			
			// Traverse down the parse tree
			if ($ast instanceof AstTerm || $ast instanceof AstFactor) {
				$left = $this->inferReturnType($ast->getLeft());
				$right = $this->inferReturnType($ast->getRight());
				
				if (($left === "float") || ($right === "float")) {
					return 'float';
				} elseif (($left === "string") || ($right === "string")) {
					return 'string';
				} else {
					return $left;
				}
			}
			
			// Default to node's declared return type
			return $ast->getReturnType();
		}
		
		/**
		 * Markeer het object als bezocht.
		 * @param AstInterface $ast
		 * @return void
		 */
		protected function addToVisitedNodes(AstInterface $ast): void {
			// Add node to the visited list
			$this->visitedNodes[spl_object_id($ast)] = true;
			
			// Also add all AstIdentifier child properties
			if ($ast instanceof AstIdentifier) {
				if ($ast->hasNext()) {
					$this->addToVisitedNodes($ast->getNext());
				}
			}
		}
		
		/**
		 * Converteer de zoekoperator naar SQL
		 * @param AstSearch $search
		 * @return void
		 */
		protected function handleSearch(AstSearch $search): void {
			$searchKey = uniqid();
			$parsed = $search->parseSearchData($this->parameters);
			$conditions = [];
			
			foreach ($search->getIdentifiers() as $identifier) {
				// Mark nodes as visited
				$this->addToVisitedNodes($identifier);
				
				// Get column name
				$entityName = $identifier->getEntityName();
				$rangeName = $identifier->getRange()->getName();
				$propertyName = $identifier->getNext()->getName();
				$columnMap = $this->entityStore->getColumnMap($entityName);
				$columnName = "{$rangeName}.{$columnMap[$propertyName]}";
				
				// Build conditions for this identifier
				$fieldConditions = [];
				$termTypes = [
					'or_terms'  => ['operator' => 'OR', 'comparison' => 'LIKE'],
					'and_terms' => ['operator' => 'AND', 'comparison' => 'LIKE'],
					'not_terms' => ['operator' => 'AND', 'comparison' => 'NOT LIKE']
				];
				
				foreach ($termTypes as $termType => $config) {
					$termConditions = [];
					
					foreach ($parsed[$termType] as $i => $term) {
						$paramName = "{$termType}{$searchKey}{$i}";
						$termConditions[] = "{$columnName} {$config['comparison']} :{$paramName}";
						$this->parameters[$paramName] = "%{$term}%";
					}
					
					if (!empty($termConditions)) {
						$fieldConditions[] = '(' . implode(" {$config['operator']} ", $termConditions) . ')';
					}
				}
				
				if (!empty($fieldConditions)) {
					$conditions[] = '(' . implode(' AND ', $fieldConditions) . ')';
				}
			}
			
			// Combine all field conditions with OR
			$this->result[] = '(' . implode(" OR ", $conditions) . ')';
		}
		
		/**
		 * Verwerkt een AstConcat-object en converteert het naar de SQL CONCAT-functie.
		 * @param AstConcat $concat Het AstConcat-object met de te verwerken parameters.
		 */
		protected function handleConcat(AstConcat $concat): void {
			// Start de CONCAT-functie in SQL.
			$this->result[] = "CONCAT(";
			
			// Loop door alle parameters van het AstConcat-object.
			$counter = 0;

			foreach($concat->getParameters() as $parameter) {
				// Als dit niet het eerste item is, voeg een komma toe.
				if ($counter > 0) {
					$this->result[] = ",";
				}
				
				// Accepteer het huidige parameterobject en verwerk het.
				$parameter->accept($this);
				++$counter;
			}
			
			// Sluit de CONCAT-functie in SQL.
			$this->result[] = ")";
		}
		
		/**
		 * Verwerkt een generieke expressie in een Abstract Syntax Tree (AST).
		 * Deze functie controleert op speciale gevallen zoals '= string', die mogelijk
		 * omgezet wordt naar een LIKE-expressie in SQL, en reguliere expressies,
		 * voordat het de standaard expressie verwerkt.
		 * @param AstInterface $ast De AST-node die de expressie representeert.
		 * @param string $operator De operator in de expressie (bijvoorbeeld '=', '<', etc.).
		 * @return void
		 */
		protected function genericHandleExpression(AstInterface $ast, string $operator): void {
			// Controleer of de operator gelijk is aan "=" of "<>", de enige ondersteunde speciale operatoren.
			if (in_array($operator, ["=", "<>"], true)) {
				// Behandel het geval waar de rechterzijde van de expressie een string is.
				if ($ast->getRight() instanceof AstString) {
					$stringAst = $ast->getRight();
					$stringValue = $stringAst->getValue();
					
					// Controleer of de stringwaarde wildcard karakters bevat.
					if (str_contains($stringValue, "*") || str_contains($stringValue, "?")) {
						// Voeg de string toe aan bezochte nodes voor mogelijke verdere verwerking.
						$this->addToVisitedNodes($stringAst);
						
						// Verwerk de linkerzijde van de expressie.
						$ast->getLeft()->accept($this);
						
						// Vervang wildcard karakters door SQL LIKE syntax equivalenten.
						$stringValue = str_replace(["%", "_", "*", "?"], ["\\%", "\\_", "%", "_"], $stringValue);
						
						// Bepaal de LIKE operator op basis van de oorspronkelijke operator.
						$regexpOperator = $operator == "=" ? " LIKE " : " NOT LIKE ";
						
						// Voeg het resultaat toe met de aangepaste operator en waarde.
						$this->result[] = "{$regexpOperator}\"{$stringValue}\"";
						return;
					}
				}
				
				// Behandel het geval waar de rechterzijde van de expressie een reguliere expressie is.
				if ($ast->getRight() instanceof AstRegExp) {
					$regexpAst = $ast->getRight();
					$stringValue = $regexpAst->getValue();

					// Voeg de reguliere expressie toe aan bezochte nodes.
					$this->addToVisitedNodes($regexpAst);

					// Verwerk de linkerzijde van de expressie.
					$ast->getLeft()->accept($this);
					
					// Bepaal de REGEXP operator op basis van de oorspronkelijke operator.
					$regexpOperator = $operator == "=" ? " REGEXP " : " NOT REGEXP ";

					// Voeg het resultaat toe met de aangepaste operator en waarde van de reguliere expressie.
					$this->result[] = "{$regexpOperator}\"{$stringValue}\"";
					return;
				}
			}
			
			// Als geen van de speciale gevallen van toepassing is, verwerk de expressie op standaard wijze.
			// Dit omvat het accepteren van de linkerzijde van de expressie, toevoegen van de operator,
			// en vervolgens het accepteren van de rechterzijde van de expressie.
			$ast->getLeft()->accept($this);
			$this->result[] = " {$operator} ";
			$ast->getRight()->accept($this);
		}

		/**
		 * Verwerkt een AstExpression-object
		 * @param AstExpression $ast Het AstExpression-object
		 * @return void
		 */
		protected function handleExpression(AstExpression $ast): void {
			$this->genericHandleExpression($ast, $ast->getOperator());
		}
		
		/**
		 * Verwerkt een AstTerm-object
		 * @param AstTerm $ast Het AstTerm-object
		 * @return void
		 */
		protected function handleTerm(AstTerm $ast): void {
			$this->genericHandleExpression($ast, $ast->getOperator());
		}
		
		/**
		 * Verwerkt een AstTerm-object
		 * @param AstFactor $ast Het AstFactor-object
		 * @return void
		 */
		protected function handleFactor(AstFactor $ast): void {
			$this->genericHandleExpression($ast, $ast->getOperator());
		}
		
		/**
		 * Verwerkt een AstBinaryOperator-object en converteert dit naar SQL met een alias.
		 * @param AstBinaryOperator $ast Het AstBinaryOperator-object
		 * @return void
		 */
		protected function handleBinaryOperator(AstBinaryOperator $ast): void {
			$this->genericHandleExpression($ast, $ast->getOperator());
		}
		
		/**
		 * Verwerkt een AstAlias-object en converteert dit naar SQL met een alias.
		 * @param AstAlias $ast Het AstAlias-object dat een expressie en een alias bevat.
		 * @return void
		 */
		protected function handleAlias(AstAlias $ast): void {
			// Verwerk een entity apart
			$expression = $ast->getExpression();
			
			if ($this->identifierIsEntity($expression)) {
				$this->addToVisitedNodes($expression);
				$this->handleEntity($expression);
				return;
			}
			
			// Verwerk de expressie die voor de alias komt.
			$ast->getExpression()->accept($this);
		}
		
		/**
		 * Voeg NOT toe aan de output stream
		 * @param AstNot $ast
		 * @return void
		 */
		protected function handleNot(AstNot $ast): void {
			$this->result[] = " NOT ";
		}
		
		/**
		 * Verwerkt een AstNull-object
		 * @param AstNull $ast Het AstNull-object
		 * @return void
		 */
		protected function handleNull(AstNull $ast): void {
			$this->result[] = 'null';
		}
		
		/**
		 * Verwerkt een AstBool-object
		 * @param AstBool $ast Het AstBool-object
		 * @return void
		 */
		protected function handleBool(AstBool $ast): void {
			$this->result[] = $ast->getValue() ? "true" : "false";
		}
		
		/**
		 * Verwerkt een AstNumber-object
		 * @param AstNumber $ast Het AstNumber-object
		 * @return void
		 */
		protected function handleNumber(AstNumber $ast): void {
			$this->result[] = $ast->getValue();
		}
		
		/**
		 * Verwerkt een AstString-object
		 * @param AstString $ast Het AstString-object
		 * @return void
		 */
		protected function handleString(AstString $ast): void {
			$this->result[] = "\"{$ast->getValue()}\"";
		}
		
		/**
		 * Verwerkt een AstIdentifier-object
		 * @param AstIdentifier $ast Het AstIdentifier-object
		 * @return void
		 */
		protected function handleIdentifier(AstIdentifier $ast): void {
			// Voeg de identifier en alle properties toe aan de 'visited nodes' lijst
			$this->addToVisitedNodes($ast);
			
			// Laat de informatie weg uit de query als de range geen database range is
			if ($ast->getRange() instanceof AstRangeJsonSource) {
				return;
			}
			
			// Haal informatie van de identifier op
			$range = $ast->getRange();
			$rangeName = $range->getName();
			$entityName = $ast->getEntityName();
			$propertyName = $ast->getNext()->getName();
			$columnMap = $this->entityStore->getColumnMap($entityName);
			
			// Als dit niet het onderdeel 'SORT BY' is, voeg dan de genormaliseerde property toe
			if ($this->partOfQuery !== "SORT") {
				$this->result[] = $rangeName . "." . $columnMap[$propertyName];
				return;
			}
			
			// Als dit wel een 'SORT BY' is, dan moeten we mogelijk een NULL waarde omzetten naar COALESCE.
			// Zonder COALESCE wordt er niet correct gesorteerd.
			$annotations = $this->entityStore->getAnnotations($entityName);
			$annotationsOfProperty = array_values(array_filter($annotations[$propertyName], function($e) { return $e instanceof Column; }));
			
			if (!$annotationsOfProperty[0]->isNullable()) {
				$this->result[] = $rangeName . "." . $columnMap[$propertyName];
			} elseif ($annotationsOfProperty[0]->getType() === "integer") {
				$this->result[] = "COALESCE({$rangeName}.{$columnMap[$propertyName]}, 0)";
			} else {
				$this->result[] = "COALESCE({$rangeName}.{$columnMap[$propertyName]}, '')";
			}
		}
		
		/**
		 * Verwerkt een AstParameter-object
		 * @param AstParameter  $ast Het AstParameter-object
		 * @return void
		 */
		protected function handleParameter(AstParameter $ast): void {
			$this->result[] = ":" . $ast->getName();
		}

		/**
		 * Verwerkt een entity
		 * @param AstIdentifier $ast
		 * @return void
		 */
		protected function handleEntity(AstIdentifier $ast): void {
			$result = [];
			$range = $ast->getRange();
			$rangeName = $range->getName();
			$columnMap = $this->entityStore->getColumnMap($ast->getEntityName());
			
			foreach($columnMap as $item => $value) {
				$result[] = "{$rangeName}.{$value} as `{$rangeName}.{$item}`";
			}
			
			$this->result[] = implode(",", $result);
		}
		
		/**
		 * Verwerkt de 'IN' conditie van een SQL-query.
		 * De 'IN' conditie wordt gebruikt om te controleren of een waarde
		 * overeenkomt met een waarde in een lijst van waarden.
		 * @param AstIn $ast Een object dat de 'IN' clausule voorstelt.
		 * @return void
		 */
		protected function handleIn(AstIn $ast): void {
			// vlag de identifier node als behandeld
			$this->visitNode($ast->getIdentifier());
			
			// Voeg de start van de 'IN' conditie toe aan het resultaat.
			$this->result[] = " IN(";
			
			// Een vlag om te controleren of we het eerste item verwerken.
			$first = true;
			
			// Doorloop elk item dat gecontroleerd moet worden binnen de 'IN' conditie.
			foreach($ast->getParameters() as $item) {
				// Als het niet het eerste item is, voeg dan een komma toe voor de scheiding.
				if (!$first) {
					$this->result[] = ",";
				}
				
				// Verwerk het item en voeg het toe aan het resultaat.
				$this->visitNode($item);

				// Zet de vlag op 'false' omdat we na het eerste item niet meer zijn.
				$first = false;
			}
			
			// Voeg de afsluitende haak toe aan de 'IN' conditie.
			$this->result[] = ")";
		}
		
		/**
		 * Parse count function
		 * @param AstInterface $ast
		 * @param bool $distinct
		 * @return void
		 */
		protected function universalHandleCount(AstInterface $ast, bool $distinct): void {
			// Verkrijg de identifier (entiteit of eigenschap) die geteld moet worden.
			$identifier = $ast->getIdentifier();
			
			// Als de identifier een entiteit is, tellen we het aantal unieke instanties van deze entiteit.
			if ($this->identifierIsEntity($identifier)) {
				// Voeg de entiteit toe aan de lijst van bezochte nodes.
				$this->addToVisitedNodes($identifier);
				
				// Verkrijg het bereik en de naam van de entiteit.
				$range = $identifier->getRange()->getName();
				$entityName = $identifier->getName();
				
				// Verkrijg de kolomnamen die de identificatie van de entiteit bepalen.
				$identifierColumns = $this->entityStore->getIdentifierColumnNames($entityName);
				
				// Voeg de COUNT DISTINCT operatie toe aan het resultaat, om unieke entiteiten te tellen.
				if ($distinct) {
					$this->result[] = "COUNT(DISTINCT {$range}.{$identifierColumns[0]})";
				} else {
					$this->result[] = "COUNT({$range}.{$identifierColumns[0]})";
				}
			}
			
			// Als de identifier een specifieke eigenschap binnen een entiteit is, tellen we hoe vaak deze eigenschap voorkomt.
			if ($identifier instanceof AstIdentifier) {
				// Voeg de eigenschap en de bijbehorende entiteit toe aan de lijst van bezochte nodes.
				$this->addToVisitedNodes($identifier);
				
				// Verkrijg het bereik van de entiteit waar de eigenschap deel van uitmaakt.
				$range = $identifier->getRange()->getName();
				
				// Verkrijg de eigenschapsnaam en de bijbehorende kolomnaam in de database.
				$property = $identifier->getNext()->getName();
				$columnMap = $this->entityStore->getColumnMap($identifier->getEntityName());
				
				// Voeg de COUNT operatie toe aan het resultaat, om de frequentie van een specifieke eigenschap te tellen.
				if ($distinct) {
					$this->result[] = "COUNT(DISTINCT {$range}.{$columnMap[$property]})";
				} else {
					$this->result[] = "COUNT({$range}.{$columnMap[$property]})";
				}
			}
		}
		
		/**
		 * Deze functie verwerkt het 'count' commando binnen een abstract syntax tree (AST).
		 * @param AstCount $count
		 * @return void
		 */
		protected function handleCount(AstCount $count): void {
			$this->universalHandleCount($count, false);
		}
		
		/**
		 * Deze functie verwerkt het 'count' commando binnen een abstract syntax tree (AST).
		 * @param AstUCount $count
		 * @return void
		 */
		protected function handleUCount(AstUCount $count): void {
			$this->universalHandleCount($count, true);
		}
		
		/**
		 * Handles 'IS NULL'. The SQL equivalent is exactly the same.
		 * @param AstCheckNull $ast
		 * @return void
		 */
        protected function handleCheckNull(AstCheckNull $ast): void {
            $this->visitNode($ast->getExpression());
            $this->result[] = " IS NULL ";
        }
        
        /**
         * Handles 'IS NOT NULL'. The SQL equivalent is exactly the same.
         * @param AstCheckNotNull $ast
         * @return void
         */
        protected function handleCheckNotNull(AstCheckNotNull $ast): void {
            $this->visitNode($ast->getExpression());
            $this->result[] = " IS NOT NULL ";
        }
		
		/**
		 * Handle is_empty function
		 * @param AstIsEmpty $ast
		 * @return void
		 */
		protected function handleIsEmpty(AstIsEmpty $ast): void {
			$this->visitNode($ast);
			
			// Fetch the node value
			$valueNode = $ast->getValue();

			// Special case for null
			if ($valueNode instanceof AstNull) {
				$this->addToVisitedNodes($valueNode);
				$this->result[] = "1";
				return;
			}
			
			// Special case for numbers
			if ($valueNode instanceof AstNumber) {
				$this->addToVisitedNodes($valueNode);
				$value = (int)$valueNode->getValue();
				$this->result[] = $value == 0 ? "1" : "0";
				return;
			}
			
			// Special case for bool
			if ($valueNode instanceof AstBool) {
				$this->addToVisitedNodes($valueNode);
				$this->result[] = !$valueNode->getValue();
				return;
			}

			// Special case for strings
			if ($valueNode instanceof AstString) {
				$this->addToVisitedNodes($valueNode);
				$this->result[] = $valueNode->getValue() === "" ? "1" : "0";
				return;
			}
			
			// Identifiers
			$inferredType = $this->inferReturnType($valueNode);
			$string = $this->visitNodeAndReturnSQL($valueNode);
			
			if (($inferredType === "integer") || ($inferredType === "float")) {
				$this->result[] = "({$string} IS NULL OR {$string} = 0)";
			} else {
				$this->result[] = "({$string} IS NULL OR {$string} = '')";
			}
		}
		
		/**
		 * Handle is_numeric function
		 * @param AstIsNumeric $ast
		 * @return void
		 */
		protected function handleIsNumeric(AstIsNumeric $ast): void {
			$this->visitNode($ast);
			
			// Fetch the node value
			$valueNode = $ast->getValue();
			
			// Special case for number. This will always be true
			if ($valueNode instanceof AstNumber) {
				$this->addToVisitedNodes($valueNode);
				$this->result[] = "1";
				return;
			}
			
			// Handle boolean and null values - they are never numeric
			if ($valueNode instanceof AstBool || $valueNode instanceof AstNull) {
				$this->addToVisitedNodes($valueNode);
				$this->result[] = "0";
				return;
			}
			
			// Handle string literals - check if the string matches a valid numeric pattern
			if ($valueNode instanceof AstString) {
				$this->addToVisitedNodes($valueNode);
				$string = "'" . addslashes($valueNode->getValue()) . "'";
				$this->result[] = "{$string} REGEXP '^-?[0-9]+(\\.[0-9]+)?$'";
				return;
			}

			// Handle identifiers (variables, function calls, etc.)
			$inferredType = $this->inferReturnType($valueNode);
			$string = $this->visitNodeAndReturnSQL($valueNode);
			
			// Return the appropriate result based on the inferred type
			if (($inferredType === 'float') || ($inferredType == "integer")) {
				$this->result[] = "1";
			} else {
				$this->result[] = "{$string} REGEXP '^-?[0-9]+(\\.[0-9]+)?$'"; // For unknown types, check if the value matches the float pattern
			}
		}
		
		/**
		 * Handles the is_integer type checking operation for different AST node types.
		 * Determines whether a given AST node represents an integer value.
		 *
		 * The function handles these cases:
		 * - String literals: checks if the string matches an integer pattern
		 * - Numbers: checks if the number contains no decimal point
		 * - Booleans: always returns false (0) as booleans are never integers
		 * - Identifiers: checks based on inferred type or pattern matching
		 * @param AstIsInteger $ast The AST node representing the is_integer check
		 * @return void
		 */
		protected function handleIsInteger(AstIsInteger $ast): void {
			// Add AstIsInteger handles nodes list
			$this->visitNode($ast);
			
			// Fetch the node value
			$valueNode = $ast->getValue();
			
			// Handle string literals - check if the string matches a valid integer pattern
			if ($valueNode instanceof AstString) {
				$this->addToVisitedNodes($valueNode);
				$string = "'" . addslashes($valueNode->getValue()) . "'";
				$this->result[] = "{$string} REGEXP '^-?[0-9]+$'";
				return;
			}
			
			// Handle numeric values - check if the number has no decimal point
			if ($valueNode instanceof AstNumber) {
				$this->addToVisitedNodes($valueNode);
				$this->result[] = !str_contains($valueNode->getValue(), ".") ? "1" : "0";
				return;
			}
			
			// Handle boolean and null values - they are never integers
			if ($valueNode instanceof AstBool || $valueNode instanceof AstNull) {
				$this->addToVisitedNodes($valueNode);
				$this->result[] = "0";
				return;
			}
			
			// Handle identifiers (variables, function calls, etc.)
			$inferredType = $this->inferReturnType($valueNode);
			$string = $this->visitNodeAndReturnSQL($valueNode);
			
			// Return appropriate result based on the inferred type
			if ($inferredType == "integer") {
				$this->result[] = "1";    // Known integer types are always integers
			} elseif ($inferredType == "float") {
				$this->result[] = "0";    // Float types are never integers
			} else {
				// For unknown types, check if the value matches an integer pattern
				$this->result[] = "{$string} REGEXP '^-?[0-9]+$'";
			}
		}
		
		/**
		 * Handles the is_float type checking operation for different AST node types.
		 * Determines whether a given AST node represents a floating point value.
		 *
		 * The function handles these cases:
		 * - String literals: checks if the string matches a float pattern
		 * - Numbers: checks if the number is not equal to its floor value
		 * - Booleans: always returns false (0) as booleans are never floats
		 * - Identifiers: checks based on inferred type or pattern matching
		 * @param AstIsFloat $ast The AST node representing the is_float check
		 * @return void
		 */
		protected function handleIsFloat(AstIsFloat $ast): void {
			$this->visitNode($ast);
			
			// Fetch the node value
			$valueNode = $ast->getValue();
			
			// Handle string literals - check if the string matches a valid float pattern
			if ($valueNode instanceof AstString) {
				$this->addToVisitedNodes($ast->getValue());
				$string = "'" . addslashes($valueNode->getValue()) . "'";
				$this->result[] = "{$string} REGEXP '^-?[0-9]+\\.[0-9]+$'";
				return;
			}
			
			// Handle numeric values - a number is a float if it's not equal to its floor value
			if ($valueNode instanceof AstNumber) {
				$this->addToVisitedNodes($ast->getValue());
				$this->result[] = str_contains($valueNode->getValue(), ".") ? "1" : "0";
				return;
			}
			
			// Handle boolean and null values - they are never floats
			if ($valueNode instanceof AstBool || $valueNode instanceof AstNull) {
				$this->addToVisitedNodes($ast->getValue());
				$this->result[] = "0";
				return;
			}
			
			// Handle identifiers (variables, function calls, etc.)
			$inferredType = $this->inferReturnType($valueNode);
			$string = $this->visitNodeAndReturnSQL($ast->getValue());
			
			// Return the appropriate result based on the inferred type
			if ($inferredType == "integer") {
				$this->result[] = "0"; // Known integer types are never floats
			} elseif ($inferredType == "float") {
				$this->result[] = "1"; // Known float types are always floats
			} else {
				$this->result[] = "{$string} REGEXP '^-?[0-9]+\\.[0-9]+$'"; // For unknown types, check if the value matches the float pattern
			}
		}
		
		/**
		 * Bezoek een knooppunt in de AST.
		 * @param AstInterface $node Het te bezoeken knooppunt.
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			// Genereer een unieke hash voor het object om duplicaten te voorkomen.
			$objectHash = spl_object_id($node);
			
			// Als het object al is bezocht, sla het dan over om oneindige lussen te voorkomen.
			if (isset($this->visitedNodes[$objectHash])) {
				return;
			}
			
			// Markeer het object als bezocht.
			$this->visitedNodes[$objectHash] = true;
			
			// Bepaal de naam van de methode die dit specifieke type Ast-node zal afhandelen.
			// De 'substr' functie wordt gebruikt om de relevante delen van de classnaam te verkrijgen.
			$className = ltrim(strrchr(get_class($node), '\\'), '\\');
			$handleMethod = 'handle' . substr($className, 3);
			
			// Controleer of de bepaalde methode bestaat en roep deze aan als dat het geval is.
			if (method_exists($this, $handleMethod)) {
				$this->{$handleMethod}($node);
			}
		}
		
		/**
		 * Bezoek een knooppunt in de AST en retourneer de SQL
		 * @param AstInterface $node Het te bezoeken knooppunt.
		 * @return string
		 */
		public function visitNodeAndReturnSQL(AstInterface $node): string {
			$pos = count($this->result);
			
			$this->visitNode($node);
			
			$slice = implode("", array_slice($this->result, $pos, 1));
			
			$this->result = array_slice($this->result, 0, $pos);
			
			return $slice;
		}
		
		/**
		 * Verkrijg de verzamelde entiteiten.
		 * @return string De geproduceerde string
		 */
		public function getResult(): string {
			return implode("", $this->result);
		}
	}