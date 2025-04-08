<?php
	
	namespace Services\ObjectQuel;
	
	use Services\AnnotationsReader\Annotations\Orm\Column;
	use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
	use Services\AnnotationsReader\Annotations\Orm\OneToMany;
	use Services\AnnotationsReader\Annotations\Orm\OneToOne;
	use Services\EntityManager\Collection;
	use Services\EntityManager\EntityCollection;
	use Services\EntityManager\entityManager;
	use Services\EntityManager\EntityStore;
	use Services\EntityManager\ProxyInterface;
	use Services\EntityManager\Serializers\Serializer;
	use Services\EntityManager\UnitOfWork;
	use Services\Kernel\PropertyHandler;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstMethodCall;
	use Services\ObjectQuel\Ast\AstRetrieve;
	use Services\ObjectQuel\Helpers\EntityHydrator;
	
	/**
	 * Represents a Quel result.
	 */
	class QuelResult implements \ArrayAccess {
		private UnitOfWork $unitOfWork;
		private entityManager $entityManager;
		private EntityStore $entityStore;
		private PropertyHandler $propertyHandler;
		private EntityHydrator $entityHydrator;
		private Serializer $serializer;
		private AstRetrieve $retrieve;
		private array $data;
		private array $result;
		private array $proxyEntityCache;
		private int $index;
		private bool $sortInApplicationLogic;
		private ?int $window;
		private ?int $pageSize;
		
		/**
		 * @param entityManager $entityManager
		 * @param AstRetrieve $retrieve
		 * @param array $data
		 */
		public function __construct(EntityManager $entityManager, AstRetrieve $retrieve, array $data) {
			$this->entityManager = $entityManager;
			$this->unitOfWork = $entityManager->getUnitOfWork();
			$this->entityStore = $entityManager->getEntityStore();
			$this->propertyHandler = $entityManager->getPropertyHandler();
			$this->serializer = new Serializer($entityManager->getEntityStore());
			$this->entityHydrator = new EntityHydrator($entityManager);
			$this->retrieve = $retrieve;
			$this->data = $data;
			$this->result = [];
			$this->proxyEntityCache = [];
			$this->index = 0;
			$this->sortInApplicationLogic = $retrieve->getSortInApplicationLogic() && empty($retrieve->getDirective('InValuesAreFinal'));
			$this->window = $retrieve->getWindow();
			$this->pageSize = $retrieve->getPageSize();
			
			// Haal de resultaten op
			$this->fetchResults();
			
			// Sorteer de resultaten indien aangegeven:
			// 1) Er wordt een method aangeroepen in SORT BY
			// 2) InValuesAreFinal is niet gezet. Bij InValuesAreFinal wordt er gesorteerd op de IN() lijst
			if ($this->sortInApplicationLogic) {
				$this->sortResults();
			}
		}
		
		/**
		 * Controleert of een specifieke entity type via een specifieke join property werd opgevraagd.
		 * @param string $targetEntity De entity class name
		 * @param string $joinProperty De specifieke join property waar we naar zoeken
		 * @return bool
		 */
		private function wasEntityRequested(string $currentEntity, string $targetEntity, string $joinProperty): bool {
			// Always return false when this is a self-referencing entity
			if ($currentEntity === $targetEntity) {
				return false;
			}
			
			// Find a range that matches the relation criteria. If one is found, return true.
			foreach ($this->retrieve->getValues() as $value) {
				// Omit non entity values
				if (!$value->getExpression() instanceof AstEntity) {
					continue;
				}
				
				// Check of de entity matched en of de join property voorkomt in de range
				$entity = $value->getExpression();
				$range = $entity->getRange();
				
				if (
					$entity->getName() === $targetEntity &&
					$range->hasJoinProperty($targetEntity, $joinProperty)
				) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Fetches results and converts them to entities, including setting up relationships.
		 * @return void
		 */
		private function fetchResults(): void {
			// Get AST values
			$ast = $this->retrieve->getValues();
			
			// Extract entities from raw data
			$result = $this->entityHydrator->hydrateEntities($ast, $this->data);
			
			// Store the result
			$this->result = $result['result'];
			
			// Set up entity relationships
			$this->setupEntityRelationships($result['entities']);
		}
		
		/**
		 * Sets up relationships between extracted entities.
		 * @param array $entities The entity objects to set up relationships for
		 * @return void
		 */
		private function setupEntityRelationships(array $entities): void {
			// Set up direct entity relationships
			$this->setRelations($entities);
			
			// Handle empty relationships by setting up proxies
			$this->promoteEmptyRelations($entities);
			
			// Handle empty OneToMany relationships
			$this->promoteEmptyOneToMany($entities);
		}
		
		/**
		 * Sorteert het resultaat
		 * @return void
		 */
		private function sortResults(): void {
			$sortItems = $this->retrieve->getSort();
			
			usort($this->result, function ($a, $b) use ($sortItems) {
				foreach ($sortItems as $sortItem) {
					$ast = $sortItem['ast'];
					$order = $sortItem['order'];
					$entity = $ast->getEntityOrParentIdentifier();
					$range = $entity->getRange()->getName();
					
					if ($ast instanceof AstMethodCall) {
						$methodName = $ast->getName();
						$aValue = $a[$range]->{$methodName}();
						$bValue = $b[$range]->{$methodName}();
					} else {
						$aValue = $a[$range];
						$bValue = $b[$range];
					}
					
					if ($aValue < $bValue) {
						return $order === 'desc' ? 1 : -1;
					} elseif ($aValue > $bValue) {
						return $order === 'desc' ? -1 : 1;
					}
				}

				return 0;
			});
		}
		
		/**
		 * Haalt de gecachete proxy-entiteitnaam op of genereert deze indien niet bestaand.
		 * @param string $targetEntityName De naam van de doelentiteit.
		 * @return string De volledige naam van de proxy-entiteit.
		 */
		private function getProxyEntityName(string $targetEntityName): string {
			if (!isset($this->proxyEntityCache[$targetEntityName])) {
                $baseEntityName = substr($targetEntityName, strrpos($targetEntityName, "\\") + 1);
				$this->proxyEntityCache[$targetEntityName] = "\\Services\\EntityManager\\Proxies\\{$baseEntityName}";
			}
			
			return $this->proxyEntityCache[$targetEntityName];
		}
		
		/**
		 * Bepaalt de juiste eigenschapnaam op de proxy op basis van de afhankelijkheid.
		 * @param $dependency mixed De OneToOne-afhankelijkheid.
		 * @return string De naam van de eigenschap.
		 */
		private function determineRelationPropertyName(mixed $dependency): string {
			return !empty($dependency->getInversedBy()) ? $dependency->getInversedBy() : $dependency->getMappedBy();
		}
		
		/**
		 * Verwerkt de afhankelijkheid van een entiteit en update de eigenschap met de gespecificeerde afhankelijkheid.
		 * Deze functie controleert of de huidige relatie null is of niet geïnitialiseerd en zoekt vervolgens
		 * naar de gerelateerde entiteit op basis van de opgegeven afhankelijkheid. Als een overeenkomstige entiteit
		 * wordt gevonden, wordt de eigenschap van de huidige entiteit bijgewerkt om deze relatie te weerspiegelen.
		 * @param object $entity De entiteit waarvan de afhankelijkheid wordt verwerkt.
		 * @param string $property De eigenschap van de entiteit die bijgewerkt moet worden.
		 * @param mixed $dependency De afhankelijkheid die gebruikt wordt om de gerelateerde entiteit te vinden.
		 */
        private function processEntityDependency(object $entity, string $property, mixed $dependency): void {
            // Verkrijg de huidige waarde van de eigenschap.
            $currentRelation = $this->propertyHandler->get($entity, $property);
            
            // Controleer of de huidige relatie al is ingesteld en of deze niet een ongeïnitialiseerde proxy is.
            if ($currentRelation !== null &&
                (!($currentRelation instanceof ProxyInterface) || $currentRelation->isInitialized())) {
                return;
            }
            
            // Bepaal de kolom en waarde voor de relatie op basis van de afhankelijkheid.
            $relationColumn = $dependency->getRelationColumn();
            $relationColumnValue = $this->propertyHandler->get($entity, $relationColumn);
            
            // Als de waarde van de relatiekolom 0 of null is, wordt de operatie niet voortgezet.
            if (empty($relationColumnValue)) {
                return;
            }
            
            // Bepaal de naam en eigenschap van de doelentiteit op basis van de afhankelijkheid.
            $targetEntityName = $dependency->getTargetEntity();
            $inversedPropertyName = $this->getInversedPropertyName($dependency);
            
            // Voeg de namespace toe aan de naam van de doelentiteit en zoek de gerelateerde entiteit.
            $targetEntity = $this->entityStore->normalizeEntityName($targetEntityName);
            $relationEntity = $this->unitOfWork->findEntity($targetEntity, [$inversedPropertyName => $relationColumnValue]);
            
            // Als een gerelateerde entiteit wordt gevonden, update dan de eigenschap van de huidige entiteit.
            if ($relationEntity !== null) {
                // Update de property met de gevonden entiteit
                // Als er een setter-method bestaat, voer deze dan uit.
                // Zet anders direct de property.
                $setterMethod = 'set' . ucfirst($property);
                
                if (method_exists($entity, $setterMethod)) {
                    $entity->{$setterMethod}($relationEntity);
                } else {
                    $this->propertyHandler->set($entity, $property, $relationEntity);
                }
            }
        }
		
		/**
		 * Bepaalt de juiste eigenschapnaam voor de inverse relatie op basis van het type afhankelijkheid.
		 * @param object $dependency De afhankelijkheid (OneToOne of ManyToOne).
		 * @return string De naam van de inverse relatie eigenschap.
		 */
		private function getInversedPropertyName(object $dependency): string {
			// Controleer het type afhankelijkheid en bepaal de juiste eigenschap.
			if ($dependency instanceof OneToOne) {
				return $dependency->getInversedBy() ?: $dependency->getMappedBy();
			} elseif ($dependency instanceof ManyToOne) {
				return $dependency->getInversedBy(); // ManyToOne heeft typisch alleen getInversedBy.
			} else {
				return '';
			}
		}
		
		/**
		 * Stelt zowel OneToOne- als ManyToOne-relaties in voor elke entiteit in de opgegeven rij.
		 * @param array $filteredEntities Een array van gefilterde entiteiten waarvoor de relaties moeten worden ingesteld.
		 * @return void
		 */
		private function setRelations(array $filteredEntities): void {
			foreach ($filteredEntities as $entity) {
				// Normaliseer de naam van de entiteitsklasse
				$entityClass = $this->entityStore->normalizeEntityName(get_class($entity));
				
				// Dependencies
				$entityDependencies = $this->entityStore->getAllDependencies($entityClass);
				
				// Controleer of er relaties zijn voor de entiteitsklasse
				if (empty($entityDependencies)) {
					continue;
				}
				
				// Itereer door elke eigenschap en zijn dependencies in de relatie-cache
				foreach ($entityDependencies as $property => $dependencies) {
					// Itereer door elke dependency van de eigenschap
					foreach ($dependencies as $dependency) {
						// Controleer of de dependency een OneToOne of ManyToOne relatie is
						if (!($dependency instanceof OneToOne) && !($dependency instanceof ManyToOne)) {
							continue;
						}
						
						// Verwerk de entity dependency
						$this->processEntityDependency($entity, $property, $dependency);
					}
				}
			}
		}
		
		/**
		 * Maakt een proxy-object aan voor een gegeven dependency en stelt deze in op de entiteit.
		 * @param object $entity De entiteit waarop de proxy wordt ingesteld
		 * @param string $property De naam van de eigenschap waar de proxy wordt ingesteld
		 * @param object $dependency Het dependency-object dat de relatie beschrijft
		 */
		private function createAndSetProxy(object $entity, string $property, object $dependency): void {
			// Bepaal de relation column
            $relationColumn = $this->getRelationColumn($entity, $dependency);
			
			// Haal de primary key waarde op. Als deze leeg is, clear dan de relatie
			$relationColumnValue = $this->propertyHandler->get($entity, $relationColumn);
			
			if (empty($relationColumnValue)) {
				$this->propertyHandler->set($entity, $property, null);
				return;
			}
			
			// Verzamel informatie om de proxy te kunnen maken
			$targetEntityName = $dependency->getTargetEntity();
			$proxyName = $this->getProxyEntityName($targetEntityName);
			
			if (($dependency instanceof ManyToOne)) {
				$relationPropertyName = $dependency->getInversedBy();
			} else {
				$relationPropertyName = $this->determineRelationPropertyName($dependency);
			}
			
			// Zoek bestaande entity
			$proxyEntity = $this->unitOfWork->findEntity($targetEntityName, [
				$relationPropertyName => $relationColumnValue
			]);
			
			// Maak een nieuwe proxy aan als er geen bestaande is gevonden
			if ($proxyEntity === null) {
				$proxyEntity = new $proxyName($this->entityManager);
				$this->propertyHandler->set($proxyEntity, $relationPropertyName, $relationColumnValue);
				$this->entityManager->persist($proxyEntity);
			}
			
			// Stel de proxy in op de originele entiteit
			$this->propertyHandler->set($entity, $property, $proxyEntity);
		}
		
		/**
		 * Filtert en retourneert een array van geldige OneToOne en ManyToOne dependencies voor een gegeven entiteit en eigenschap.
		 * @param object $entity De entiteit waarvan de eigenschap wordt gecontroleerd.
		 * @param string $property De naam van de eigenschap van de entiteit.
		 * @param array $dependencies Een array van dependencies om te filteren.
		 * @return array Een array van geldige OneToOne en ManyToOne dependencies.
		 */
		private function filterValidDependencies(object $entity, string $property, array $dependencies): array {
			$validDependencies = [];
			
			foreach ($dependencies as $dependency) {
				// Controleer of de dependency een instantie is van OneToOne of ManyToOne
				if (!($dependency instanceof OneToOne) && !($dependency instanceof ManyToOne)) {
					// Ga verder naar de volgende iteratie als de dependency geen OneToMany is
					continue;
				}
				
				// Haal de waarde van de eigenschap op uit de entiteit
				$propertyValue = $this->propertyHandler->get($entity, $property);
				
				// Voeg de waarde toe aan de lijst van geldige dependencies
				if ($propertyValue === null) {
					$validDependencies[] = $dependency;
				}
			}
			
			return $validDependencies;
		}
		
		/**
		 * Filtert en retourneert een array van geldige OneToMany dependencies voor een gegeven entiteit en eigenschap.
		 * @param object $entity De entiteit waarvan de eigenschap wordt gecontroleerd.
		 * @param string $property De naam van de eigenschap van de entiteit.
		 * @param array $dependencies Een array van dependencies om te filteren.
		 * @return array Een array van geldige OneToMany dependencies.
		 */
		private function filterEmptyOneToManyDependencies(object $entity, string $property, array $dependencies): array {
			$validDependencies = [];

			foreach ($dependencies as $dependency) {
				// Controleer of de dependency een instantie is van OneToMany
				if (!($dependency instanceof OneToMany)) {
					// Ga verder naar de volgende iteratie als de dependency geen OneToMany is
					continue;
				}
				
				// Haal de waarde van de eigenschap op uit de entiteit
				$propertyValue = $this->propertyHandler->get($entity, $property);
				
				// Voeg de waarde toe aan de lijst van geldige dependencies
				if ($propertyValue instanceof Collection && $propertyValue->isEmpty()) {
					$validDependencies[] = $dependency;
				}
			}
			
			// Retourneer de array van geldige dependencies
			return $validDependencies;
		}
		
		/**
		 * Bepaalt de relationColumn voor een gegeven entiteit en dependency.
		 * @param object $entity De entiteit waarvoor de relationColumn wordt bepaald
		 * @param object $dependency Het dependency object
		 * @return string De naam van de relationColumn
		 */
		private function getRelationColumn(object $entity, object $dependency): string {
			$relationColumn = $dependency->getRelationColumn();

			if (empty($relationColumn)) {
				$primaryKeys = $this->entityStore->getIdentifierKeys($entity);
				$relationColumn = $primaryKeys[0];
			}

			return $relationColumn;
		}
		
		/**
		 * Promoot lege relaties naar proxy-objecten voor de gegeven gefilterde rijen.
		 * @param array $filteredRows De rijen die verwerkt moeten worden
		 * @return void
		 */
		private function promoteEmptyRelations(array $filteredRows): void {
			// Loop door alle gefilterde rijen
			foreach ($filteredRows as $value) {
				// Haal de genormaliseerde naam van de entity klasse
				$objectClass = $this->entityStore->normalizeEntityName(get_class($value));
				
				// Verkrijg alle afhankelijkheden van de entity klasse
				$entityDependencies = $this->entityStore->getAllDependencies($objectClass);
				
				// Loop door alle eigenschappen en hun afhankelijkheden
				foreach ($entityDependencies as $property => $dependencies) {
					// Filter de geldige afhankelijkheden voor de huidige waarde en eigenschap
					$validDependencies = $this->filterValidDependencies($value, $property, $dependencies);
					
					// Maak en stel een proxy in voor elke geldige afhankelijkheid
					foreach ($validDependencies as $dependency) {
						$this->createAndSetProxy($value, $property, $dependency);
					}
				}
			}
		}
		
		/**
		 * Promoot lege OneToMany-relaties naar lazy-loaded collecties voor de gegeven gefilterde rijen.
		 * @param array $filteredRows De rijen die verwerkt moeten worden
		 * @return void
		 */
		private function promoteEmptyOneToMany(array $filteredRows): void {
			// Loop door alle gefilterde rijen
			foreach ($filteredRows as $value) {
				// Haal de genormaliseerde naam van de entity klasse
				$objectClass = $this->entityStore->normalizeEntityName(get_class($value));
				
				// Verkrijg alle afhankelijkheden van de entity klasse
				$entityDependencies = $this->entityStore->getAllDependencies($objectClass);
				
				// Loop door alle eigenschappen en hun afhankelijkheden
				foreach ($entityDependencies as $property => $dependencies) {
					// Filter lege One-to-Many afhankelijkheden voor de huidige waarde en eigenschap
					$validDependencies = $this->filterEmptyOneToManyDependencies($value, $property, $dependencies);
					
					// Maak en stel een collectie van entities in voor elke geldige afhankelijkheid
					foreach ($validDependencies as $dependency) {
						$targetEntity = $this->entityStore->normalizeEntityName($dependency->getTargetEntity());
						$relationColumn = $this->getRelationColumn($value, $dependency);
						$mappedBy = $dependency->getMappedBy();

						// Doe niets als de data voor deze query wel opgevraagd is. Er is dan simpelweg geen data,
						// dus het heeft geen zin om deze data alsnog te laxy loaden. We houden dan de lege collectie.
						if ($this->wasEntityRequested($objectClass, $targetEntity, $mappedBy)) {
							continue;
						}
						
						// Maak een Entity Collection aan
						$primaryKeyValue = $this->propertyHandler->get($value, $relationColumn);
						
						$proxy = new EntityCollection(
							$this->entityManager, $targetEntity, $dependency->getMappedBy(),
							$primaryKeyValue, $dependency->getOrderBy()
						);
						
						$this->propertyHandler->set($value, $property, $proxy);
					}
				}
			}
		}
		
		/**
		 * Returns the number of rows inside this recordset
		 * @return int
		 */
		public function recordCount(): int {
			return count($this->result);
		}
		
		/**
		 * Reads a row of a result set and advances the recordset pointer
		 * @return array|false
		 */
		public function fetchRow(): array|false {
			if ($this->index >= $this->recordCount()) {
				return false;
			}
			
			$result = $this->result[$this->index];
			++$this->index;
			return $result;
		}
        
        /**
         * Retourneert de waarde van $columnName voor alle rows tegelijk
         * @param string|int $columnName
         * @return array
         */
		public function fetchCol(string|int $columnName=0): array {
            if (is_int($columnName)) {
                $keys = array_keys($this->result);
                $columnName = $keys[$columnName];
            }
            
			return array_column($this->result, $columnName);
		}
		
		/**
		 * Moves the result index to the given position
		 * @param int $pos
		 * @return void
		 */
		public function seek(int $pos): void {
			$this->index = $pos;
		}
		
		/**
		 * Returns true if sort and pagination are done in application logic, false if using mysql
		 * @return bool
		 */
		public function getSortInApplicationLogic(): bool {
			return $this->sortInApplicationLogic;
		}
		
		/**
		 * Returns the pagination window, or null if there is none
		 * @return int|null
		 */
		public function getWindow(): ?int {
			return $this->window;
		}

		/**
		 * Returns the pagination page_size, or null if there is none
		 * @return int|null
		 */
		public function getPageSize(): ?int {
			return $this->pageSize;
		}
		
		/**
		 * Returns the raw data in this recordset
		 * @return array
		 */
		public function getResults(): array {
			return $this->result;
		}
		
		/**
		 * Apply a custom transformation function to the entire result set
		 * @param callable $transformer Function that transforms the entire result array
		 * @return $this Returns $this for method chaining
		 */
		public function transform(callable $transformer): self {
			$this->result = $transformer($this->result);
			return $this;
		}
		
		/**
		 * Filters the entire result set
		 * @param callable $condition Function that takes the result array and returns filtered array
		 * @return $this For method chaining
		 */
		public function filter(callable $condition): self {
			$this->result = $condition($this->result);
			return $this;
		}
		
		/**
		 * Merge another QuelResult or array of rows into this one
		 * @param array|QuelResult $otherResult The result to merge
		 * @return $this Returns $this for method chaining
		 */
		public function merge(array|QuelResult $otherResult): self {
			if ($otherResult instanceof QuelResult) {
				$this->result = array_merge($this->result, $otherResult->getResults());
			} else {
				$this->result = array_merge($this->result, $otherResult);
			}
			
			return $this;
		}
		
		/**
		 * Extract values for a specific field from the result set
		 * @param string $field The field to extract
		 * @return array Array of extracted values
		 */
		public function extractFieldValues(string $field): array {
			$values = [];
			
			// Implementation depends on how your QuelResult stores data
			// This is a generic example:
			foreach ($this->getResults() as $result) {
				if (is_object($result)) {
					// Handle entity objects using getter methods
					$getter = 'get' . ucfirst($field);
					
					if (method_exists($result, $getter)) {
						$values[] = $result->$getter();
					} elseif (property_exists($result, $field)) {
						// Try property access if getter doesn't exist
						$values[] = $result->$field;
					}
				} elseif (is_array($result) && isset($result[$field])) {
					// Handle array results
					$values[] = $result[$field];
				}
			}
			
			// Remove duplicates and null values
			return array_values(array_filter(array_unique($values)));
		}
		
		public function offsetExists(mixed $offset): bool {
			return isset($this->result[$offset]);
		}
		
		public function offsetGet(mixed $offset): mixed {
			return $this->result[$offset] ?? null;
		}
		
		public function offsetSet(mixed $offset, mixed $value): void {
			if (is_null($offset)) {
				$this->result[] = $value;
			} else {
				$this->result[$offset] = $value;
			}
		}
		
		public function offsetUnset(mixed $offset): void {
			unset($this->result[$offset]);
		}
	}