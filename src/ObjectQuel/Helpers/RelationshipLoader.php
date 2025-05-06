<?php
	
	namespace Quellabs\ObjectQuel\ObjectQuel\Helpers;
	
	use Quellabs\ObjectQuel\Annotations\Orm\ManyToOne;
	use Quellabs\ObjectQuel\Annotations\Orm\OneToMany;
	use Quellabs\ObjectQuel\Annotations\Orm\OneToOne;
	use Quellabs\ObjectQuel\EntityManager;
	use Quellabs\ObjectQuel\EntityManager\Collections\Collection;
	use Quellabs\ObjectQuel\EntityManager\Collections\EntityCollection;
	use Quellabs\ObjectQuel\EntityManager\EntityStore;
	use Quellabs\ObjectQuel\EntityManager\Proxy\ProxyInterface;
	use Quellabs\ObjectQuel\EntityManager\Reflection\PropertyHandler;
	use Quellabs\ObjectQuel\EntityManager\UnitOfWork;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstIdentifier;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstRangeDatabase;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstRetrieve;
	use Quellabs\ObjectQuel\ObjectQuel\AstInterface;
	
	class RelationshipLoader {
		
		private AstRetrieve $retrieve;
		private UnitOfWork $unitOfWork;
		private EntityManager $entityManager;
		private EntityStore $entityStore;
		private PropertyHandler $propertyHandler;
		private array $proxyEntityCache;
		
		public function __construct(EntityManager $entityManager, AstRetrieve $retrieve) {
			$this->retrieve = $retrieve;
			$this->entityManager = $entityManager;
			$this->unitOfWork = $entityManager->getUnitOfWork();
			$this->entityStore = $entityManager->getEntityStore();
			$this->propertyHandler = $entityManager->getPropertyHandler();
			$this->proxyEntityCache = [];
		}
		
		/**
		 * Haalt de gecachete proxy-entiteitnaam op of genereert deze indien niet bestaand.
		 * @param string $targetEntityName De naam van de doelentiteit.
		 * @return string De volledige naam van de proxy-entiteit.
		 */
		private function getProxyEntityName(string $targetEntityName): string {
			if (!isset($this->proxyEntityCache[$targetEntityName])) {
				$baseEntityName = substr($targetEntityName, strrpos($targetEntityName, "\\") + 1);
				$this->proxyEntityCache[$targetEntityName] = "\\Quellabs\ObjectQuel\\EntityManager\\Proxies\\{$baseEntityName}";
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
				if (!$this->identifierIsEntity($value->getExpression())) {
					continue;
				}
				
				// Check of de entity matched en of de join property voorkomt in de range
				$entity = $value->getExpression();
				$range = $entity->getRange();
				
				if (
					$entity->getEntityName() === $targetEntity &&
					$range->hasJoinProperty($targetEntity, $joinProperty)
				) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Stelt zowel OneToOne- als ManyToOne-relaties in voor elke entiteit in de opgegeven rij.
		 * @param array $filteredEntities Een array van gefilterde entiteiten waarvoor de relaties moeten worden ingesteld.
		 * @return void
		 */
		private function setDirectRelations(array $filteredEntities): void {
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
		 * Promoot lege relaties naar proxy-objecten voor de gegeven gefilterde rijen.
		 * @param array $filteredRows De rijen die verwerkt moeten worden
		 * @return void
		 */
		private function setupProxyRelations(array $filteredRows): void {
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
		private function setupOneToManyCollections(array $filteredRows): void {
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
		 * Loads all relationships for a set of entities
		 * @param array $entities The entities to load relationships for
		 */
		public function loadRelationships(array $entities): void {
			// Set direct entity-to-entity relationships
			$this->setDirectRelations($entities);
			
			// Set up proxies for empty relationships
			$this->setupProxyRelations($entities);
			
			// Set up collections for empty OneToMany relations
			$this->setupOneToManyCollections($entities);
		}
	}