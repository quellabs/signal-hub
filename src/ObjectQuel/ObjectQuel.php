<?php
	
	namespace Services\ObjectQuel;
	
	use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
	use Services\AnnotationsReader\Annotations\Orm\OneToOne;
	use Services\EntityManager\DatabaseAdapter;
	use Services\EntityManager\EntityManager;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstAnd;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstExists;
	use Services\ObjectQuel\Ast\AstIn;
	use Services\ObjectQuel\Ast\AstNumber;
	use Services\ObjectQuel\Ast\AstOr;
	use Services\ObjectQuel\Visitors\ContainsCheckIsNullForRange;
    use Services\AnnotationsReader\Annotations\Orm\RequiredRelation;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRangeDatabase;
	use Services\ObjectQuel\Ast\AstRetrieve;
	use Services\ObjectQuel\Visitors\AddNamespacesToEntities;
	use Services\ObjectQuel\Visitors\AliasPlugAliasPattern;
	use Services\ObjectQuel\Visitors\ContainsMethodCall;
	use Services\ObjectQuel\Visitors\ContainsRange;
	use Services\ObjectQuel\Visitors\EntityExistenceValidator;
	use Services\ObjectQuel\Visitors\EntityPlugMacros;
	use Services\ObjectQuel\Visitors\AddRangeToEntityWhenItsMissing;
	use Services\ObjectQuel\Visitors\EntityProcessMacro;
	use Services\ObjectQuel\Visitors\EntityProcessRange;
	use Services\ObjectQuel\Visitors\EntityPropertyValidator;
	use Services\ObjectQuel\Visitors\FetchMethodCalls;
	use Services\ObjectQuel\Visitors\GetMainEntityInAst;
	use Services\ObjectQuel\Visitors\GetMainEntityInAstException;
	use Services\ObjectQuel\Visitors\NoExpressionsAllowedOnEntitiesValidator;
	use Services\ObjectQuel\Visitors\RangeOnlyReferencesOtherRanges;
	use Services\ObjectQuel\Visitors\TransformRelationInViaToPropertyLookup;
	use Services\ObjectQuel\Visitors\ValidateRelationInViaValid;
    
    class ObjectQuel {
		
		private EntityStore $entityStore;
		private EntityManager $entityManager;
		private DatabaseAdapter $connection;
		private int $fullQueryResultCount;
		
		/**
		 * Constructor om de EntityManager te injecteren.
		 * @param EntityManager $entityManager
		 */
		public function __construct(EntityManager $entityManager) {
			$this->entityManager = $entityManager;
			$this->entityStore = $entityManager->getEntityStore();
			$this->connection = $entityManager->getConnection();
			$this->fullQueryResultCount = 0;
		}
		
		/**
		 * Valideert dat er geen dubbele range-namen zijn in de AST van een Quel-query.
		 * Deze functie controleert of elke range in de AstRetrieve-query een unieke naam heeft.
		 * Als er dubbele range-namen worden gevonden, wordt een QuelException geworpen.
		 * @param AstRetrieve $ast De AST-representatie van de Quel-query.
		 * @return void
		 * @throws QuelException Als er dubbele range-namen worden gedetecteerd.
		 */
		private function validateNoDuplicateRanges(AstRetrieve $ast): void {
			// Verzamel alle range-namen uit de AST.
			$rangeNames = array_map(function ($e) { return $e->getName(); }, $ast->getRanges());
			
			// Vind en verzamel alle dubbele range-namen.
			$duplicateNames = [];
			
			foreach (array_count_values($rangeNames) as $name => $count) {
				if ($count > 1) {
					$duplicateNames[] = $name;
				}
			}
			
			// Als er dubbele namen zijn, gooi dan een uitzondering met een foutmelding.
			if (!empty($duplicateNames)) {
				throw new QuelException("Duplicate range name(s) detected. The range names " . implode(', ', $duplicateNames) . " have been defined more than once. Each range name must be unique within a query. Please revise the query to use distinct range names.");
			}
		}
		
		/**
		 * Takes all referenced ranges, looks up the accompanied entities and stores them in the AstIdentifier ASTs
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function processRanges(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'EntityProcessRange' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new EntityProcessRange($ast->getRanges());
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Looks into 'via' clauses for relations and transforms them into property lookups
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function transformViaRelationsIntoProperties(AstRetrieve $ast): void {
			foreach($ast->getRanges() as $range) {
				$joinProperty = $range->getJoinProperty();

				if ($joinProperty === null) {
					continue;
				}
				
				$converter = new TransformRelationInViaToPropertyLookup($this->entityStore, $range);
				$range->setJoinProperty($converter->processNodeSide($joinProperty));
				$range->accept($converter);
			}
		}
		
		/**
		 * Takes all referenced ranges, looks up the accompanied entities and stores them in the AstIdentifier ASTs
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function processMacros(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'EntityProcessRange' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new EntityProcessMacro($ast->getMacros());
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Validates that referenced properties exist in the entity
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function validateProperties(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'EntityPropertyValidator' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new EntityPropertyValidator($this->entityStore);
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Voegt bereiken toe aan de AstRetrieve-instantie.
		 * Deze functie haalt de bereiken op via de EntityPlugRange-klasse
		 * en voegt ze vervolgens toe aan de gegeven AstRetrieve-instantie.
		 * @param AstRetrieve $ast De instantie van AstRetrieve waar de bereiken aan toegevoegd worden.
		 * @return void
		 */
		private function plugRanges(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'EntityPlugRange' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new AddRangeToEntityWhenItsMissing();
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
			
			// Voeg de toegevoegde ranges toe aan de AST
			foreach($rangeProcessor->getRanges() as $range) {
				$ast->addRange($range);
			}
		}
		
		/**
		 * Voegt een alias pattern toe waarmee het makkelijker wordt om
		 * entities terug te vinden in de result set
		 * @param AstRetrieve $ast De instantie van AstRetrieve waar de bereiken aan toegevoegd worden.
		 * @return void
		 */
		private function plugAliasPatterns(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'AliasPlugAliasPattern' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new AliasPlugAliasPattern();
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Validates that the referenced entities exist.
		 * The function assumes all ranges are processed and completed.
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function validateEntitiesExist(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'EntityExistenceValidator' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new EntityExistenceValidator($this->entityStore);
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Complete namespaces
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function addNamespacesToEntities(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'AddNamespacesToEntities' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new AddNamespacesToEntities($this->entityStore, $ast->getRanges(), $ast->getMacros());
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Takes macros defined in the retrieve/column section and integrates them into the query
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function plugMacros(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'EntityPropertyValidator' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new EntityPlugMacros($ast->getMacros());
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Controleert of een array een object van een bepaalde klasse bevat.
		 * @param string $needle De naam van de klasse die gezocht wordt in de array.
		 * @param array $haystack De array waarin gezocht wordt naar een object van de gegeven klasse.
		 * @return bool Geeft 'true' terug als een object van de opgegeven klasse wordt gevonden in de array, anders 'false'.
		 */
		private function inArrayObject(string $needle, array $haystack): bool {
			foreach ($haystack as $item) {
				if ($item instanceof $needle) {
					return true;
				}
			}
			
			return false;
		}
		
		
		/**
		 * Controleert of de 'joinProperty' een geldige 'AstExpression' is met 'AstIdentifier' aan beide zijden.
		 * @param mixed $joinProperty
		 * @return bool
		 */
		private function isExpressionWithTwoIdentifiers($joinProperty): bool {
			return $joinProperty instanceof AstExpression &&
				$joinProperty->getLeft() instanceof AstIdentifier &&
				$joinProperty->getRight() instanceof AstIdentifier;
		}
	    
	    /**
	     * Verwerkt de annotaties van de gerelateerde entiteit voor een specifieke 'range'.
	     * De functie controleert of de 'range' als verplicht moet worden ingesteld op basis van
	     * de 'ManyToOne' annotaties van de gerelateerde entiteit.
	     * @param AstRangeDatabase $mainRange De hoofdrange voor deze query
	     * @param AstRangeDatabase $range Het 'range' object dat mogelijk als verplicht wordt gemarkeerd.
	     * @param AstIdentifier $left De linker identifier in de joinProperty van de 'range'.
	     * @param AstIdentifier $right De rechter identifier in de joinProperty van de 'range'.
	     * @return void
	     */
		private function setRangeRequiredIfNeeded(AstRangeDatabase $mainRange, AstRangeDatabase $range, AstIdentifier $left, AstIdentifier $right): void {
			// Properties
			$isMainRange = $right->getEntityOrParentIdentifier()->getRange() === $mainRange;
			$ownPropertyName = $isMainRange ? $right->getName() : $left->getName();
			$ownEntityName = $isMainRange ? $right->getEntityOrParentIdentifier()->getName() : $left->getEntityName();
			$relatedPropertyName = $isMainRange ? $left->getName() : $right->getName();
			$relatedEntityName = $isMainRange ? $left->getEntityName() : $right->getEntityOrParentIdentifier()->getName();
			
			// Ophalen van de annotaties van de gerelateerde entiteit.
			$entityAnnotations = $this->entityStore->getAnnotations($ownEntityName);
			
			// Doorloop elke annotatiegroep van de gerelateerde entiteit.
			foreach ($entityAnnotations as $annotations) {
				// Ga door naar de volgende groep als RequiredRelation annotatie niet aanwezig is.
				if (!$this->inArrayObject(RequiredRelation::class, $annotations)) {
					continue;
				}
				
				// Controleer elke annotatie binnen de groep.
				foreach ($annotations as $annotation) {
					// Controleer of de annotatie een ManyToOne relatie is die overeenkomt met de opgegeven criteria.
					if (
						($annotation instanceof ManyToOne || $annotation instanceof OneToOne) &&
						$annotation->getTargetEntity() === $relatedEntityName &&
						$annotation->getRelationColumn() === $ownPropertyName &&
						$annotation->getInversedBy() === $relatedPropertyName
					) {
						// Markeer de range als verplicht en stop de verwerking.
						$range->setRequired();
						return;
					}
				}
			}
		}
		
		/**
		 * Make the range required when it's used inside the WHERE clause
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function setRangesRequiredThroughWhere(AstRetrieve $ast): void {
            if ($ast->getConditions() !== null) {
                foreach ($ast->getRanges() as $range) {
                    try {
                        if (!$range->isRequired()) {
                            $visitor = new ContainsRange($range->getName());
                            $ast->getConditions()->accept($visitor);
                        }
                    } catch (\Exception $e) {
                        $range->setRequired();
                    }
                }
            }
		}
        
        /**
		 * Make the range required when it's used inside the WHERE clause
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function setRangesNotRequiredThroughWhere(AstRetrieve $ast): void {
            if ($ast->getConditions() !== null) {
                foreach ($ast->getRanges() as $range) {
                    try {
                        if ($range->isRequired()) {
                            $visitor = new ContainsCheckIsNullForRange($range->getName());
                            $ast->getConditions()->accept($visitor);
                        }
                    } catch (\Exception $e) {
                        $range->setRequired(false);
                    }
                }
            }
		}
	 
		/**
	     * Returns the main range of the range list. E.g. the first one without a join property
	     * @param AstRetrieve $ast
	     * @return AstRangeDatabase|null
	     */
	    private function getMainRange(AstRetrieve $ast): ?AstRangeDatabase {
		    foreach($ast->getRanges() as $range) {
			    if ($range->getJoinProperty() == null) {
				    return $range;
			    }
		    }
		    
		    return null;
	    }
		
		/**
		 * Deze functie stelt 'range'-objecten in als verplicht, gebaseerd op bepaalde voorwaarden.
		 * Het controleert de 'joinProperty' van elk 'range'-object in de gegeven 'AstRetrieve'-instantie.
		 * @param AstRetrieve $ast Een object dat de te controleren 'range'-objecten bevat.
		 * @return void
		 */
		private function setRangesRequiredThroughRequiredRelationAnnotation(AstRetrieve $ast): void {
			$mainRange = $this->getMainRange($ast);
			
			foreach ($ast->getRanges() as $range) {
				// Verifieer of 'joinProperty' een valide 'AstExpression' is met 'AstIdentifier' aan beide zijden.
				if (!$this->isExpressionWithTwoIdentifiers($range->getJoinProperty())) {
					continue;
				}
				
				// Haal linker en rechterdeel van de join property op
				$joinProperty = $range->getJoinProperty();
				$left = $joinProperty->getLeft();
				$right = $joinProperty->getRight();
				
				// Draai de twee om, als het rechterdeel de huidige range target
				if ($right->getEntityOrParentIdentifier()->getName() == $range->getEntity()->getName()) {
					$tmp = $left;
					$left = $right;
					$right = $tmp;
				}
				
				// Als het linkerdeel niet de huidige range hit, dan kan hij zeker niet required worden
				if ($left->getEntityOrParentIdentifier()->getName() !== $range->getEntity()->getName()) {
					continue;
				}
				
				// Zoek een RequiredRelation annotatie op
				$this->setRangeRequiredIfNeeded($mainRange, $range, $left, $right);
			}
		}
		
		/**
		 * Controleert dat er geen operaties zoals '+' worden uitgevoerd op hele entities.
		 * Dit kan alleen maar op properties.
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function validateNoExpressionsAllowedOnEntitiesValidator(AstRetrieve $ast): void {
			// Creëert een nieuwe instantie van 'EntityExistenceValidator' met de bereiken die uit het AST-object worden opgehaald.
			$rangeProcessor = new NoExpressionsAllowedOnEntitiesValidator();
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Controleer of de sort een method call bevat. Zoja, zet dan de sort_in_application_logic flag
		 * zodat de sorteerlogica en paginering logica in de applicatie gebeurd in plaats van in de query.
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function setSortInApplicationFlag(AstRetrieve $ast): void {
			try {
				$containsMethodCallVisitor = new ContainsMethodCall();
				
				foreach ($ast->getSort() as $item) {
					$item['ast']->accept($containsMethodCallVisitor);
				}
			} catch (\Exception $exception) {
				$ast->setSortInApplicationLogic(true);
			}
		}
		
		/**
		 * Verwerkt sorteerlogica door de benodigde entiteiten volledig in te lezen als de 'sort in application' vlag actief is.
		 *
		 * Deze functie controleert eerst of de sorteerlogica in de applicatie zelf moet worden uitgevoerd.
		 * Als dit het geval is, controleert de functie vervolgens of de 'InValuesAreFinal' richtlijn aanwezig is.
		 * Als 'InValuesAreFinal' aanwezig is, wordt de verdere verwerking overgeslagen.
		 * Anders verzamelt de functie alle methodeaanroepen binnen de sorteeroperatie en controleert of
		 * de complete entiteit van elke methodeaanroep al geladen is. Zo niet, dan wordt deze toegevoegd aan de AST.
		 * @param AstRetrieve $ast De AST die mogelijk aangepast moet worden om alle benodigde data in te laden.
		 */
		private function plugCompleteEntityIfSortInApplicationFlagSet(AstRetrieve $ast): void {
			// Controleer of sorteren in de applicatie logica nodig is.
			if (!$ast->getSortInApplicationLogic()) {
				return;
			}
			
			// Controleer of de 'InValuesAreFinal' richtlijn ingesteld is, wat betekent dat geen verdere sorteerlogica nodig is.
			if (!empty($ast->getDirective('InValuesAreFinal'))) {
				return;
			}
			
			// Verzamel alle methodeaanroepen die relevant zijn voor de sorteeroperatie.
			$methodCallsVisitor = new FetchMethodCalls();
			foreach ($ast->getSort() as $item) {
				$item['ast']->accept($methodCallsVisitor);
			}
			
			// Controleer elke methodeaanroep om te zien of de volledige entiteit al geladen is, en voeg deze toe indien nodig.
			foreach ($methodCallsVisitor->getResult() as $method) {
				// Controleer of de entiteit al onderdeel is van de waarden die ingelezen worden.
				$entityName = $method->getEntityOrParentIdentifier()->getName();
				$range = $method->getEntityOrParentIdentifier()->getRange();
				
				foreach ($ast->getValues() as $value) {
					if ($value->getName() == $range->getName()) {
						continue 2;
					}
				}
				
				// Voeg de complete entiteit toe aan de AST.
				$astEntity = new AstEntity($entityName, clone $range);
				$alias = new AstAlias($range->getName(), $astEntity, "{$range->getName()}.");
				$ast->addValue($alias);
			}
		}
	 
		private function handleExistsOperatorHelperSetParent(AstInterface $parent, AstInterface $item, bool $parentLeft): void {
			if ($parent instanceof AstRetrieve) {
				$parent->setConditions($item);
			} elseif ($parentLeft) {
				$parent->setLeft($item);
			} else {
				$parent->setRight($item);
			}
		}
		
	    /**
	     * Handles the EXISTS operator in Abstract Syntax Tree (AST) transformations
	     *
	     * Recursively processes EXISTS operators by:
	     * 1. Handling nested AND/OR operations
	     * 2. Replacing EXISTS nodes with their conditions
	     * 3. Updating parent node connections
	     * @param AstInterface|null $parent Parent node in AST
	     * @param AstInterface $item Current node being processed
	     * @param array &$list Reference to list being populated
	     * @param bool $parentLeft Whether current item is left child of parent
	     */
	    private function handleExistsOperatorHelper(?AstInterface $parent, AstInterface $item, array &$list, bool $parentLeft = false): void {
		    // Process left branch for AND/OR operations
		    if ($item->getLeft() instanceof AstOr || $item->getLeft() instanceof AstAnd) {
			    $this->handleExistsOperatorHelper($item, $item->getLeft(), $list, true);
		    }
		    
		    // Process right branch for AND/OR operations
		    if ($item->getRight() instanceof AstOr || $item->getRight() instanceof AstAnd) {
			    $this->handleExistsOperatorHelper($item, $item->getRight(), $list, false);
		    }
		    
		    // Get left and right nodes
		    $left = $item->getLeft();
		    $right = $item->getRight();

			// Special case for 'exist AND/OR exists' as only condition
		    if ($parent instanceof AstRetrieve && $left instanceof AstExists && $right instanceof AstExists) {
			    $list[] = $left;
			    $list[] = $right;
				$parent->setConditions(null);
				return;
		    }
		    
		    // Handle EXISTS operator in left branch
		    if ($left instanceof AstExists) {
			    $list[] = $left;
				$this->handleExistsOperatorHelperSetParent($parent, $right, $parentLeft);
		    }
		    
		    // Handle EXISTS operator in right branch
		    if ($right instanceof AstExists) {
			    $list[] = $right;
			    $this->handleExistsOperatorHelperSetParent($parent, $left, $parentLeft);
		    }
	    }
		
	    /**
	     * This function locates all uses of EXISTS(<entity>) in the WHERE sections.
	     * It then removes the keyword from the query (because it can't be translated to SQL).
	     * For every EXISTS it sets the accompanied range to mandatory. This forces an INNER JOIN.
	     * @param AstRetrieve $ast
	     * @return void
	     */
		private function handleExistsOperator(AstRetrieve $ast): void {
			// Fetch the conditions from the AST
			$conditions = $ast->getConditions();
			
			// No conditions. Do nothing
			if ($conditions === null) {
				return;
			}
			
			// If the only condition is AstExists. Clear the conditions.
			// If the condition is 'exists AND exists' or 'exists OR exists' clear the condition.
			// Otherwise use recursion to fetch and remove all exists operators.
			$astExistsList = [];

			if ($conditions instanceof AstExists) {
				$astExistsList = [$conditions];
				$ast->setConditions(null);
			} elseif ($conditions instanceof AstOr || $conditions instanceof AstAnd) {
				$this->handleExistsOperatorHelper($ast, $conditions, $astExistsList);
			}

			// Set all targeted ranges to mandatory
			foreach ($astExistsList as $exists) {
				$existsRange = $exists->getEntity()->getRange();
				
				foreach($ast->getRanges() as $range) {
					if ($range->getName() == $existsRange->getName()) {
						$range->setRequired();
						continue 2;
					}
				}
			}
		}
		
		/**
		 * Valideert dat methodeaanroepen enkel worden gebruikt in SORT BY clausules binnen de AST.
		 * Deze functie doorloopt de AST-componenten om te controleren of er ergens methodeaanroepen zijn gebruikt.
		 * Het controleert specifiek de join properties van alle ranges en de condities van de AST.
		 * Als er een methodeaanroep wordt gevonden buiten een SORT BY clausule, wordt een QuelException geworpen.
		 * @param AstRetrieve $ast De AST (Abstract Syntax Tree) die gevalideerd moet worden.
		 * @throws QuelException Als er methodeaanroepen worden gevonden buiten SORT BY clausules.
		 */
		private function validateMethodCallNotUsedInAnythingOtherThanSort(AstRetrieve $ast): void {
			try {
				$containsMethodCallVisitor = new ContainsMethodCall();
				
				// Doorloop elke range in de AST en controleer de join properties op methodeaanroepen.
				foreach ($ast->getRanges() as $item) {
					$item->getJoinProperty()?->accept($containsMethodCallVisitor);
				}
				
				// Controleer ook de condities van de AST op methodeaanroepen.
				$ast->getConditions()?->accept($containsMethodCallVisitor);
			} catch (\Exception $exception) {
				throw new QuelException("Method calls are only allowed in SORT BY.");
			}
		}
		
		/**
		 * Validates the given AstRetrieve object.
		 * @param AstRetrieve $ast The AstRetrieve object to be validated.
		 * @return void
		 * @throws QuelException
		 */
		private function validateAstRetrieve(AstRetrieve $ast): void {
			// Voeg macro's in
			$this->plugMacros($ast);
			
			// Valideer dat aangemaakte ranges uniek zijn
			$this->validateNoDuplicateRanges($ast);
			
			// Zoekt identifiers op die worden aangesproken op hun range naam, en vervangt deze door de entity naam.
			// De range wordt in het range-gedeelte van de entity gezet.
			$this->processRanges($ast);
			
			// Zoekt entities op die worden aangesproken op hun alias. Het is niet mogelijk om condities
			// te bouwen op hele entities, dus dit geeft later een foutmelding.
			$this->processMacros($ast);
			
			// Als entities op hun eigen naam worden aangesproken, maak dan impliciet een range aan.
			$this->plugRanges($ast);
			
			// Valideer dat er tenminste één range is zonder via clausule
			$this->validateAtLeastOneRangeExistsWithoutViaClause($ast);
			
			// Ranges mogen alleen andere ranges aanspreken. Dit wordt hier gevalideerd
			$this->ensureRangesOnlyReferenceOtherRanges($ast);
			
			// Voeg namespaces toe aan entities
			$this->addNamespacesToEntities($ast);
			
			// Valideer dat aangesproken entities bestaan
			$this->validateEntitiesExist($ast);
			
			// Als er directe relaties worden gebruikt in de 'via' clausule, valideer deze dan op geldigheid
			$this->validateRangeViaRelations($ast);
			
			// Zoekt aangesproken relaties op in de 'via' clausule, en zet ze om in property lookups
			$this->transformViaRelationsIntoProperties($ast);
			
			// Valideer dat properties binnen entities bestaan
			$this->validateProperties($ast);
			
			// Valideer dat er geen hele entities worden gebruikt als condities.
			$this->validateNoExpressionsAllowedOnEntitiesValidator($ast);
			
			// Throw error when method calls are used in anything but 'sort by'
			$this->validateMethodCallNotUsedInAnythingOtherThanSort($ast);
			
			// Voeg 'alias patterns' toe indien nodig. Deze patterns maken het makkelijk
			// om gegevens uit het SQL-resultaat te hydrateren.
			$this->plugAliasPatterns($ast);

			// Analyse ranges and set the required flag if its 'via' clause matches a RequiredRelation
			$this->setRangesRequiredThroughRequiredRelationAnnotation($ast);

			// Analyse query and set the required flag if a range is used in the where clause
			$this->setRangesRequiredThroughWhere($ast);
	
			// Analyse where and clear the required flag if 'is null' used on the join column
			$this->setRangesNotRequiredThroughWhere($ast);
			
			// Add a 'sort in application' flag when the sort conditions contain a method call
			$this->setSortInApplicationFlag($ast);
			
			// Add complete entity to fetch list if 'sort in application' is set and it's not there
			$this->plugCompleteEntityIfSortInApplicationFlagSet($ast);
			
			// Handle exists() operator
			$this->handleExistsOperator($ast);
		}
		
		/**
		 * @param AstRetrieve $ast
		 * @return void
		 * @throws QuelException
		 */
		private function validateAtLeastOneRangeExistsWithoutViaClause(AstRetrieve $ast): void {
			foreach($ast->getRanges() as $range) {
				if ($range->getJoinProperty() === null) {
					return;
				}
			}
			
			throw new QuelException("The query must include at least one range definition without a 'via' clause. This serves as the 'FROM' clause in SQL and is essential for defining the data source for the query.");
		}
		
		/**
		 * Ensures that ranges only refer to other ranges.
		 * @param AstRetrieve $ast The AST object.
		 * @return void
		 * @throws QuelException
		 */
		private function ensureRangesOnlyReferenceOtherRanges(AstRetrieve $ast): void {
			$rangeOnlyOtherRanges = new RangeOnlyReferencesOtherRanges();
			
			foreach ($ast->getRanges() as $range) {
				try {
					$joinProperty = $range->getJoinProperty();
					
					if ($joinProperty !== null) {
						$joinProperty->accept($rangeOnlyOtherRanges);
					}
				} catch (QuelException $e) {
					throw new QuelException(sprintf($e->getMessage(), $range->getName()));
				}
			}
		}
		
		/**
		 * Ensures that ranges only refer to other ranges.
		 * @param AstRetrieve $ast The AST object.
		 * @return void
		 * @throws QuelException
		 */
		private function validateRangeViaRelations(AstRetrieve $ast): void {
			foreach ($ast->getRanges() as $range) {
				try {
					$joinProperty = $range->getJoinProperty();
					
					if ($joinProperty !== null) {
						$rangeOnlyOtherRanges = new ValidateRelationInViaValid($this->entityStore, $range->getEntity()->getName(), $range->getName());
						$joinProperty->accept($rangeOnlyOtherRanges);
					}
				} catch (QuelException $e) {
					throw new QuelException(sprintf($e->getMessage(), $range->getName()));
				}
			}
		}
		
		
		/**
		 * Default way of adding pagination
		 * @param AstRetrieve $e
		 * @param array $parameters
		 * @param array $primaryKeyInfo
		 * @return void
		 */
		private function addPaginationDataToQueryDefault(AstRetrieve &$e, array $parameters, array $primaryKeyInfo): void {
			// Bewaar de originele query waardes om later te herstellen.
			$originalValues = $e->getValues();
			$originalUnique = $e->getUnique();
			
			// Forceer de query om unieke resultaten te retourneren.
			$e->setUnique(true);
			
			// Maak een nieuw AST-element voor de primaire sleutel.
			$astEntity = new AstEntity($primaryKeyInfo['entityName'], clone $primaryKeyInfo['range']);
			$astIdentifier = new AstIdentifier($astEntity, $primaryKeyInfo['primaryKey']);
			$e->setValues([new AstAlias("primary", $astIdentifier)]);
			
			// Converteer de aangepaste AstRetrieve naar SQL en voer uit.
			$sql = $this->convertToSQL($e);
			$primaryKeys = $this->connection->GetCol($sql, $parameters);
			$this->fullQueryResultCount = count($primaryKeys);
			
			// Filter de primaire sleutels voor de specifieke paginatie window.
			$primaryKeysFiltered = array_slice($primaryKeys, $e->getWindow() * $e->getPageSize(), $e->getPageSize());
			$newParameters = array_map(function($item) { return new AstNumber($item); }, $primaryKeysFiltered);
			
			// Herstel de originele query waarden.
			$e->setValues($originalValues);
			$e->setUnique($originalUnique);
			
			// Kijk of AstIn al in de query voorkomt. Zo ja, vervang dan de parameters
			try {
				$visitor = new GetMainEntityInAst($astIdentifier);
				$e->getConditions()->accept($visitor);
			} catch (GetMainEntityInAstException $exception) {
				$exception->getAstObject()->setParameters($newParameters);
				return;
			}
			
			// Creeër een AstIn-condition met de gefilterde primaire sleutels.
			$astIn = new AstIn($astIdentifier, $newParameters);
			
			// Voeg de nieuwe condition toe aan de bestaande conditions of vervang deze.
			if ($e->getConditions() === null) {
				$e->setConditions($astIn);
			} else {
				$e->setConditions(new AstAnd($e->getConditions(), $astIn));
			}
		}
		
		/**
		 * Directly manipulate the values in 'IN()' without extra queries
		 * @param AstRetrieve $e
		 * @param array $parameters
		 * @param array $primaryKeyInfo
		 * @return void
		 */
		private function addPaginationDataToQuerySkipInValidation(AstRetrieve &$e, array $parameters, array $primaryKeyInfo): void {
			try {
				// Maak een AstIdentifier waarmee we kunnen zoeken naar een IN()
				$astEntity = new AstEntity($primaryKeyInfo['entityName'], clone $primaryKeyInfo['range']);
				$astIdentifier = new AstIdentifier($astEntity, $primaryKeyInfo['primaryKey']);
				
				// Zoek de IN() op in de query. An exception thrown here means it's found and is not an error
				$visitor = new GetMainEntityInAst($astIdentifier);
				$e->getConditions()->accept($visitor);
				
				// Als de IN() niet is gevonden, ga dan door met default logica
				$this->addPaginationDataToQueryDefault($e, $parameters, $primaryKeyInfo);
			} catch (GetMainEntityInAstException $exception) {
				$astObject = $exception->getAstObject();
				
				// Sla de lengte van de parameters op
				$this->fullQueryResultCount = count($astObject->getParameters());
				
				// Pas de IN() lijst aan
				$primaryKeysFiltered = array_slice($astObject->getParameters(), $e->getWindow() * $e->getPageSize(), $e->getPageSize());
				$astObject->setParameters($primaryKeysFiltered);
			}
		}
		
		/**
		 * Voegt paginatiegegevens toe aan een AstRetrieve query door de query te manipuleren
		 * om alleen de primaire sleutels van de gevraagde pagina op te halen.
		 * Dit gebeurt door de query eerst te transformeren om alleen de primaire sleutels terug te geven,
		 * vervolgens de relevante subset van primaire sleutels op te halen gebaseerd op de paginatie parameters,
		 * en uiteindelijk de originele query te herstellen met een aangepaste voorwaarde die enkel de gefilterde sleutels bevat.
		 * @param AstRetrieve $e
		 * @param array $parameters
		 * @return void
		 */
		private function addPaginationDataToQuery(AstRetrieve &$e, array $parameters): void {
			// Controleer en haal de primaire sleutel informatie op.
			$primaryKeyInfo = $this->entityStore->fetchPrimaryKeyOfMainRange($e);
			
			if ($primaryKeyInfo === null) {
				return;
			}
			
			// Als de compiler directive @SkipInValidation is meegegeven, skip dan de extra
			// Queries en werkt direct met de IN waardes
			$compilerDirectives = $e->getDirectives();
			
			if (isset($compilerDirectives['InValuesAreFinal']) && ($compilerDirectives['InValuesAreFinal'] === true)) {
				$this->addPaginationDataToQuerySkipInValidation($e, $parameters, $primaryKeyInfo);
			} else {
				$this->addPaginationDataToQueryDefault($e, $parameters, $primaryKeyInfo);
			}
		}
		
		/**
         * Parses a Quel query and returns its AST representation.
         * This function takes a Quel query string and processes it through a lexer and parser
         * to generate an Abstract Syntax Tree (AST) representation of the query. It returns
         * the AST if the parsing is successful and the AST is of type `AstRetrieve`.
         * Otherwise, it returns null.
         * @param string $query The Quel query string.
		 * @param array $parameters
		 * @return AstInterface|null The AST representation of the query or null if parsing fails.
         * @throws QuelException If there is a problem during lexing or parsing.
         */
        public function parse(string $query, array $parameters=[]): ?AstInterface {
            try {
                // Initialize the lexer with the query to tokenize the input.
                $lexer = new Lexer($query);
                
                // Initialize the parser with the lexer to interpret the tokens and create the AST.
                $parser = new Parser($lexer);
                
                // Parse the query to generate the AST.
                $ast = $parser->parse();
                
                // Check if the generated AST is of type AstRetrieve.
                // If it is not, handle the unexpected AST type by returning null.
                if (!$ast instanceof AstRetrieve) {
                    // Handle unexpected AST type, log error, or throw exception
                    return null;
                }
                
                // Validate the retrieved AST to ensure it meets the expected criteria.
                $this->validateAstRetrieve($ast);
				
				// Als de query paginering gebruikt ('window x using page_size y'), dan moeten we
				// de query aanpassen om te bepalen welke primary keys vallen binnen het window.
				if (($ast->getWindow() !== null) && !$ast->getSortInApplicationLogic()) {
					$this->addPaginationDataToQuery($ast, $parameters);
				}
				
				// Return the valid AST.
                return $ast;
            } catch (LexerException | ParserException $e) {
                // Catch lexer and parser exceptions, wrap them in a QuelException, and rethrow.
                throw new QuelException($e->getMessage());
            }
        }

		/**
		 * Convert AstRetrieve node to SQL
		 * @param AstRetrieve $retrieve
		 * @param array $parameters
		 * @return string
		 */
		public function convertToSQL(AstRetrieve $retrieve, array &$parameters): string {
			$quelToSQL = new QuelToSQL($this->entityStore, $parameters);
			return $quelToSQL->convertToSQL($retrieve);
		}
		
		/**
		 * Returns the full query result count when paginating
		 * @return int
		 */
		public function getFullQueryResultCount(): int {
			return $this->fullQueryResultCount;
		}
	}