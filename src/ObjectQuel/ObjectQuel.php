<?php
	
	namespace Services\ObjectQuel;
	
	use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
	use Services\AnnotationsReader\Annotations\Orm\OneToOne;
    use Services\ObjectQuel\Visitors\ContainsCheckIsNullForRange;
    use Services\AnnotationsReader\Annotations\Orm\RequiredRelation;
	use Services\EntityManager\EntityManager;
	use Services\EntityManager\EntityStore;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRange;
	use Services\ObjectQuel\Ast\AstRetrieve;
	use Services\ObjectQuel\Visitors\AddNamespacesToEntities;
	use Services\ObjectQuel\Visitors\AliasPlugAliasPattern;
	use Services\ObjectQuel\Visitors\ContainsRange;
	use Services\ObjectQuel\Visitors\EntityExistenceValidator;
	use Services\ObjectQuel\Visitors\EntityPlugMacros;
	use Services\ObjectQuel\Visitors\AddRangeToEntityWhenItsMissing;
	use Services\ObjectQuel\Visitors\EntityProcessMacro;
	use Services\ObjectQuel\Visitors\EntityProcessRange;
	use Services\ObjectQuel\Visitors\EntityPropertyValidator;
	use Services\ObjectQuel\Visitors\NoExpressionsAllowedOnEntitiesValidator;
	use Services\ObjectQuel\Visitors\RangeOnlyReferencesOtherRanges;
	use Services\ObjectQuel\Visitors\TransformRelationInViaToPropertyLookup;
	use Services\ObjectQuel\Visitors\ValidateRelationInViaValid;
	
	class ObjectQuel {
		
		/**
		 * De EntityManager instantie.
		 * @var EntityManager
		 */
		private EntityManager $entityManager;
		
		/**
		 * De EntityStore instantie.
		 * @var EntityStore
		 */
		private EntityStore $entityStore;
		
		/**
		 * De Quel to SQL instantie.
		 * @var $quelToSQL QuelToSQL
		 */
		private QuelToSQL $quelToSQL;
		
		/**
		 * Constructor om de EntityManager te injecteren.
		 * @param EntityManager $entityManager
		 */
		public function __construct(EntityManager $entityManager) {
			$this->entityManager = $entityManager;
			$this->entityStore = $entityManager->getUnitOfWork()->getEntityStore();
			$this->quelToSQL = new QuelToSQL($entityManager);
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
				
				$converter = new TransformRelationInViaToPropertyLookup($this->entityManager, $range);
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
			$rangeProcessor = new EntityPropertyValidator($this->entityManager);
			
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
			$rangeProcessor = new EntityExistenceValidator($this->entityManager);
			
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
			$rangeProcessor = new AddNamespacesToEntities($this->entityManager, $ast->getRanges(), $ast->getMacros());
			
			// Het AST-object accepteert de 'rangeProcessor' voor verdere verwerking.
			$ast->accept($rangeProcessor);
		}
		
		/**
		 * Takes macros defined in the retrieve/column section and integrates them into the query
		 * @param AstRetrieve $ast
		 * @return void
		 */
		private function plugMacros(AstRetrieve $ast) {
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
		 * @param AstRange $range Het 'range' object dat mogelijk als verplicht wordt gemarkeerd.
		 * @param AstIdentifier $left De linker identifier in de joinProperty van de 'range'.
		 * @param AstIdentifier $right De rechter identifier in de joinProperty van de 'range'.
		 * @return void
		 */
		private function setRangeRequiredIfNeeded(AstRange $range, AstIdentifier $left, AstIdentifier $right): void {
			$ownPropertyName = $right->getPropertyName();
			$ownEntityName = $right->getEntity()->getName();
			$relatedPropertyName = $left->getPropertyName();
			$relatedEntityName = $left->getEntityName();
			
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
		 * Deze functie stelt 'range'-objecten in als verplicht, gebaseerd op bepaalde voorwaarden.
		 * Het controleert de 'joinProperty' van elk 'range'-object in de gegeven 'AstRetrieve'-instantie.
		 * @param AstRetrieve $ast Een object dat de te controleren 'range'-objecten bevat.
		 * @return void
		 */
		private function setRangesRequiredThroughRequiredRelationAnnotation(AstRetrieve $ast): void {
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
				if ($right->getEntity()->getName() == $range->getEntity()->getName()) {
					$tmp = $left;
					$left = $right;
					$right = $tmp;
				}
				
				// Als het linkerdeel niet de huidige range hit, dan kan hij zeker niet required worden
				if ($left->getEntity()->getName() !== $range->getEntity()->getName()) {
					continue;
				}
				
				// Zoek een RequiredRelation annotatie op
				$this->setRangeRequiredIfNeeded($range, $left, $right);
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
			
			// Als er directe relaties worden gebruikt in de 'via' clausule, valideer deze dan op geldigheid
			$this->validateRangeViaRelations($ast);
			
			// Zoekt aangesproken relaties op in de 'via' clausule, en zet ze om in property lookups
			$this->transformViaRelationsIntoProperties($ast);
			
			// Voeg namespaces toe aan entities
			$this->addNamespacesToEntities($ast);

			// Valideer dat aangesproken entities bestaan
			$this->validateEntitiesExist($ast);
			
			// Valideer dat properties binnen entities bestaan
			$this->validateProperties($ast);
			
			// Valideer dat er geen hele entities worden gebruikt als condities.
			$this->validateNoExpressionsAllowedOnEntitiesValidator($ast);

			// Voeg 'alias patterns' toe indien nodig. Deze patterns maken het makkelijk
			// om gegevens uit het SQL-resultaat te hydrateren.
			$this->plugAliasPatterns($ast);

			// Analyse ranges and set the required flag if its 'via' clause matches a RequiredRelation
			$this->setRangesRequiredThroughRequiredRelationAnnotation($ast);

			// Analyse query and set the required flag if a range is used in the where clause
			$this->setRangesRequiredThroughWhere($ast);
	
			// Analyse where and clear the required flag if 'is null' used on the join colun
			$this->setRangesNotRequiredThroughWhere($ast);
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
						$rangeOnlyOtherRanges = new ValidateRelationInViaValid($this->entityManager, $range->getEntity()->getName(), $range->getName());
						$joinProperty->accept($rangeOnlyOtherRanges);
					}
				} catch (QuelException $e) {
					throw new QuelException(sprintf($e->getMessage(), $range->getName()));
				}
			}
		}
	
		/**
		 * Parses a Quel query and returns its AST representation.
		 * @param string $query The Quel query string.
		 * @return AstInterface|null The AST representation of the query or null if parsing fails.
		 * @throws QuelException
		 */
		public function parse(string $query): ?AstInterface {
			try {
				$lexer = new Lexer($query);
				$parser = new Parser($lexer);
				$ast = $parser->parse();
			} catch (LexerException | ParserException $e) {
				throw new QuelException($e->getMessage());
			}
			
			if (!$ast instanceof AstRetrieve) {
				// Handle unexpected AST type, log error, or throw exception
				return null;
			}
			
			$this->validateAstRetrieve($ast);
			return $ast;
		}
		
		/**
		 * @param AstRetrieve $retrieve
		 * @return string
		 */
		public function convertToSQL(AstRetrieve $retrieve): string {
			return $this->quelToSQL->convertToSQL($retrieve);
		}
	}