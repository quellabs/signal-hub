<?php
	
	// Namespace declaration voor gestructureerde code
	namespace Services\ObjectQuel\Visitors;
	
	// Importeer de vereiste klassen en interfaces
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstAnd;
	use Services\ObjectQuel\Ast\AstBool;
	use Services\ObjectQuel\Ast\AstConcat;
	use Services\ObjectQuel\Ast\AstCount;
	use Services\ObjectQuel\Ast\AstEntity;
    use Services\ObjectQuel\Ast\AstCheckNull;
    use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstFactor;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstIn;
	use Services\ObjectQuel\Ast\AstNot;
	use Services\ObjectQuel\Ast\AstNull;
	use Services\ObjectQuel\Ast\AstNumber;
	use Services\ObjectQuel\Ast\AstOr;
	use Services\ObjectQuel\Ast\AstParameter;
	use Services\ObjectQuel\Ast\AstRegExp;
	use Services\ObjectQuel\Ast\AstString;
	use Services\ObjectQuel\Ast\AstTerm;
	use Services\ObjectQuel\Ast\AstUCount;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
    use Services\ObjectQuel\Ast\AstCheckNotNull;
    
    /**
	 * Class RetrieveEntities
	 * Implementeert AstVisitor om entiteiten uit een AST te verzamelen.
	 */
	class QuelToSQLConvertToString implements AstVisitorInterface {
		
		// De entity store voor entity naar table conversies
		private EntityStore $entityStore;
		
		// Array om verzamelde entiteiten op te slaan
		private array $result;
		private array $visitedNodes;
		private string $partOfQuery;
		
		/**
		 * Constructor om de entities array te initialiseren.
		 * @param EntityStore $store
		 * @param string $partOfQuery
		 */
		public function __construct(EntityStore $store, string $partOfQuery="VALUES") {
			$this->result = [];
			$this->visitedNodes = [];
			$this->entityStore = $store;
			$this->partOfQuery = $partOfQuery;
		}
		
		/**
		 * Markeer het object als bezocht.
		 * @param AstInterface $ast
		 * @return void
		 */
		protected function addToVisitedNodes(AstInterface $ast): void {
			$this->visitedNodes[spl_object_hash($ast)] = true;
		}
		
		/**
		 * Verwerkt een AstConcat-object en converteert het naar de SQL CONCAT-functie.
		 * @param AstConcat $concat Het AstConcat-object met de te verwerken parameters.
		 */
		protected function handleConcat(AstConcat $concat) {
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
				$concat->accept($parameter);
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
		 * Verwerkt een AstAnd-object en converteert dit naar SQL met een alias.
		 * @param AstAnd $ast Het AstAnd-object
		 * @return void
		 */
		protected function handleAnd(AstAnd $ast): void {
			$this->genericHandleExpression($ast, "AND");
		}
		
		/**
		 * Verwerkt een AstOr-object
		 * @param AstOr $ast Het AstOr-object
		 * @return void
		 */
		protected function handleOR(AstOr $ast): void {
			$this->genericHandleExpression($ast, "OR");
		}
		
		/**
		 * Verwerkt een AstAlias-object en converteert dit naar SQL met een alias.
		 * @param AstAlias $ast Het AstAlias-object dat een expressie en een alias bevat.
		 * @return void
		 */
		protected function handleAlias(AstAlias $ast): void {
			// Verwerk een entity apart
			$expression = $ast->getExpression();
			
			if ($expression instanceof AstEntity) {
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
		 * @param string $alias
		 * @return void
		 */
		protected function handleIdentifier(AstIdentifier $ast, string $alias=""): void {
			$this->addToVisitedNodes($ast->getEntity());
			
			$range = $ast->getEntity()->getRange();
			$rangeName = $range->getName();
			$columnMap = $this->entityStore->getColumnMap($ast->getEntity()->getName());
			
			$this->result[] = $rangeName . "." . $columnMap[$ast->getPropertyName()];
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
		 * Verwerkt een AstEntity-object
		 * @param AstEntity $ast
		 * @return void
		 */
		protected function handleEntity(AstEntity $ast): void {
			$result = [];
			$range = $ast->getRange();
			$rangeName = $range->getName();
			$columnMap = $this->entityStore->getColumnMap($ast->getName());
			
			foreach($columnMap as $item => $value) {
				$result[] = "{$rangeName}.{$value} as `{$rangeName}.{$item}`";
			}
			
			$this->result[] = implode(",", $result);
		}
		
		/**
		 * Verwerkt de 'IN' conditie van een SQL query.
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
		
		protected function universalHandleCount(AstInterface $ast, bool $distinct): void {
			// Verkrijg de identifier (entiteit of eigenschap) die geteld moet worden.
			$identifier = $ast->getIdentifier();
			
			// Als de identifier een entiteit is, tellen we het aantal unieke instanties van deze entiteit.
			if ($identifier instanceof AstEntity) {
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
				$this->addToVisitedNodes($identifier->getEntity());
				
				// Verkrijg het bereik van de entiteit waar de eigenschap deel van uitmaakt.
				$range = $identifier->getEntity()->getRange()->getName();
				
				// Verkrijg de eigenschapsnaam en de bijbehorende kolomnaam in de database.
				$property = $identifier->getPropertyName();
				$columnMap = $this->entityStore->getColumnMap($identifier->getEntity()->getName());
				
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
         * Handles 'IS NULL'. The SQL-equivalent is exactly the same.
         * @param AstCheckNotNull $ast
         * @return void
         */
        protected function handleCheckNull(AstCheckNull $ast): void {
            $this->visitNode($ast->getExpression());
            $this->result[] = " IS NULL ";
        }
        
        /**
         * Handles 'IS NOT NULL'. The SQL-equivalent is exactly the same.
         * @param AstCheckNotNull $ast
         * @return void
         */
        protected function handleCheckNotNull(AstCheckNotNull $ast): void {
            $this->visitNode($ast->getExpression());
            $this->result[] = " IS NOT NULL ";
        }
        
		/**
		 * Bezoek een knooppunt in de AST.
		 * @param AstInterface $node Het te bezoeken knooppunt.
		 * @return void
		 */
		public function visitNode(AstInterface $node): void {
			// Genereer een unieke hash voor het object om duplicaten te voorkomen.
			$objectHash = spl_object_hash($node);
			
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
		 * Verkrijg de verzamelde entiteiten.
		 * @return string De geproduceerde string
		 */
		public function getResult(): string {
			return implode("", $this->result);
		}
	}