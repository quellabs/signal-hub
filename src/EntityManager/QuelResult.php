<?php
	
	namespace Services\EntityManager;
	
	use Services\AnnotationsReader\Annotations\Orm\Column;
	use Services\AnnotationsReader\Annotations\Orm\ManyToOne;
	use Services\AnnotationsReader\Annotations\Orm\OneToOne;
	use Services\ObjectQuel\Ast\AstAlias;
	use Services\ObjectQuel\Ast\AstEntity;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstRetrieve;
	
	/**
	 * Represents a Quel result.
	 */
	class QuelResult {
		private UnitOfWork $unitOfWork;
		private entityManager $entityManager;
		private EntityStore $entityStore;
		private PropertyHandler $propertyHandler;
		private AstRetrieve $retrieve;
		private \ADORecordSet $rs;
		private array $result;
		private array $proxyEntityCache;
		private int $index;
		
		/**
		 * QuelResult constructor
		 * @param UnitOfWork $unitOfWork
		 * @param AstRetrieve $retrieve
		 * @param \ADORecordSet $rs
		 */
		public function __construct(UnitOfWork $unitOfWork, AstRetrieve $retrieve, \ADORecordSet $rs) {
			$this->unitOfWork = $unitOfWork;
			$this->entityManager = $unitOfWork->getEntityManager();
			$this->entityStore = $unitOfWork->getEntityStore();
			$this->propertyHandler = $unitOfWork->getPropertyHandler();
			$this->retrieve = $retrieve;
			$this->rs = $rs;
			$this->result = [];
			$this->proxyEntityCache = [];
			$this->index = 0;
			
			// Haal de resultaten op
			$this->fetchResults();
		}
		
		/**
		 * Haalt resultaten op en converteert deze naar entiteiten, inclusief het instellen van relaties.
		 * Deze methode haalt alle rijen op, converteert gegevens naar entiteiten en stelt diverse
		 * soorten relaties in. Het maakt ook relaties 'lazy' indien deze leeg zijn, ter optimalisatie
		 * van de laadtijd en geheugengebruik.
		 * @return void
		 */
		private function fetchResults(): void {
			$ast = $this->retrieve->getValues();
			
			while ($row = $this->rs->fetchRow()) {
				$updatedRow = [];
				
				// Converteer rijdata naar entiteiten of andere relevante data.
				foreach ($ast as $value) {
					$updatedRow[$value->getName()] = $this->processValue($value, $row);
				}
				
				// Stel de verschillende relaties in voor de opgehaalde data en maak deze lazy indien nodig.
				$this->setRelations($updatedRow);
				$this->promoteEmptyRelations($updatedRow);
				$this->promoteEmptyOneToMany($updatedRow);
				
				// Voeg het verwerkte resultaat toe aan de resultatenlijst.
				$this->result[] = $updatedRow;
			}
		}
		
		/**
		 * Bepaalt of een gegeven string overeenkomt met een specifiek patroon.
		 * Het patroon kan de volgende wildcards bevatten:
		 * '*' staat voor een willekeurige reeks karakters (of geen).
		 * '?' staat voor één willekeurig karakter.
		 * @param string $pattern Het te matchen patroon.
		 * @param string $string De te controleren string.
		 * @return bool Geeft aan of de string overeenkomt met het patroon.
		 */
		private function matchPattern(string $pattern, string $string): bool {
			$patternLength = strlen($pattern);
			$stringLength = strlen($string);
			
			for ($i = 0, $j = 0; $i < $patternLength && $j < $stringLength; $i++, $j++) {
				$patternChar = $pattern[$i];
				
				if ($patternChar === '*') {
					// Controleer of '*' het laatste karakter in het patroon is.
					if ($i === $patternLength - 1) {
						return true; // Als dit het geval is, matcht het patroon met de rest van de string.
					}
					
					// Sla de karakters in de string over tot het volgende karakter in het patroon gevonden is.
					while ($j < $stringLength && $string[$j] !== $pattern[$i + 1]) {
						$j++;
					}
					
					if ($j === $stringLength) {
						return false; // Einde van de string bereikt zonder match.
					}
				} elseif ($patternChar === '?') {
					// '?' matcht met elk enkel karakter, ga verder.
					continue;
				} elseif ($patternChar !== $string[$j]) {
					return false; // Directe mismatch gevonden.
				}
			}
			
			// Controleer of het hele patroon en de string doorlopen zijn.
			// Dit zorgt ervoor dat er geen overgebleven karakters zijn in zowel het patroon als de string.
			return $i === $patternLength && $j === $stringLength;
		}
		
		/**
		 * Filtert een array op basis van een patroon en geeft een nieuwe array terug
		 * met alleen die elementen waarvan de keys overeenkomen met het patroon.
		 * Deze functie gebruikt de matchPattern-functie om te bepalen of een key
		 * overeenkomt met het gegeven patroon.
		 *
		 * Wildcards in het patroon:
		 * - '*' staat voor een willekeurige reeks karakters (of geen).
		 * - '?' staat voor één willekeurig karakter.
		 * @param string $pattern Het patroon waarmee keys vergeleken worden.
		 * @param array $array De array waarvan de elementen gefilterd worden.
		 * @return array Een nieuwe array met alleen de elementen die overeenkomen met het patroon.
		 */
		private function patternMatchRow(string $pattern, array $array): array {
			$result = [];
			
			foreach ($array as $key => $value) {
				if ($this->matchPattern($pattern, $key)) {
					$result[$key] = $value;
				}
			}
			
			return $result;
		}
		
		/**
		 * Remove a specified range from the keys of an array.
		 * @param string $range The range to remove from the array keys.
		 * @param array $array The array to modify.
		 * @return array The modified array with the range removed from the keys.
		 */
		private function removeRangeFromRow(string $range, array $array): array {
			// Gebruik array_map om de keys aan te passen
			$modifiedKeys = array_map(function($key) use ($range) {
				return str_replace("{$range}.", '', $key);
			}, array_keys($array));
			
			// Combineer de nieuwe keys met de oorspronkelijke waarden
			return array_combine($modifiedKeys, array_values($array));
		}
		
		/**
		 * Verwerkt een enkele waarde uit het resultaat.
		 * @param mixed $value De te verwerken waarde.
		 * @param array $row De huidige rij uit de database.
		 * @return mixed
		 */
		private function processValue(mixed $value, array $row): mixed {
			// Fetch identifier
			if ($value->getExpression() instanceof AstIdentifier) {
				$expression = $value->getExpression();
				$value = $row[$value->getName()];
				$annotations = $this->entityStore->getAnnotations($expression->getEntityName());
				$annotationsForProperty = $annotations[$expression->getPropertyName()];
				
				foreach ($annotationsForProperty as $annotation) {
					if ($annotation instanceof Column) {
						return $this->unitOfWork->normalizeValue($annotation, $value);
					}
				}
				
				return null;
			}
			
			// Fetch Entity
			if ($value->getExpression() instanceof AstEntity) {
				$aliasPattern = $value->getAliasPattern();
				$filteredRow = $this->patternMatchRow($aliasPattern, $row);
				return $this->processEntity($value, $filteredRow);
			}
			
			// Otherwise try to fetch the data from the row
			return $row[$value->getName()] ?? null;
		}
		
		/**
		 * Verwerkt een entity op basis van de gegeven waarde en gefilterde rij.
		 * Retourneert `null` als de rij geen waarden bevat, wat duidt op een mislukte LEFT JOIN.
		 * Creëert een nieuwe entity als deze niet bestaat, of retourneert de bestaande.
		 * @param AstAlias $value De alias met de expressie voor entity naam en bereik.
		 * @param array $filteredRow De gefilterde rijgegevens van de database.
		 * @return object|null De gevonden of nieuw aangemaakte entity, of `null`.
		 */
		private function processEntity(AstAlias $value, array $filteredRow): object|null {
			if (empty(array_filter($filteredRow))) {
				return null; // Vroege terugkeer bij lege rij.
			}
			
			$entity = $value->getExpression()->getName();
			$rangeName = $value->getExpression()->getRange()->getName();
			$filteredRow = $this->removeRangeFromRow($rangeName, $filteredRow);
			$primaryKeys = $this->unitOfWork->getEntityStore()->getIdentifierKeys($entity);
			$primaryKeyValues = array_intersect_key($filteredRow, array_flip($primaryKeys));
			$existingEntity = $this->unitOfWork->findEntity($entity, $primaryKeyValues);

			if ($existingEntity !== null) {
				if (($existingEntity instanceof ProxyInterface) && !$existingEntity->isInitialized()) {
					// Neem de gegevens in de entity over en geef aan dat de proxy nu ingeladen is
					$this->unitOfWork->deserializeEntity($existingEntity, $filteredRow);
					$existingEntity->setInitialized();
					
					// Ontkoppel de entity zodat deze weer als bestaande entity kan worden toegevoegd
					$this->unitOfWork->detach($existingEntity);
				}

				// Persist de entity voor latere flushes
				$this->unitOfWork->persistExisting($existingEntity);
				
				// Bestaande entity teruggeven.
				return $existingEntity;
			}
			
			// Nieuwe entity aanmaken en teruggeven.
			$newEntity = new $entity;
			$this->unitOfWork->deserializeEntity($newEntity, $filteredRow);
			$this->unitOfWork->persistExisting($newEntity);
			return $newEntity;
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
		 * naar de gerelateerde entiteit op basis van de opgegeven afhankelijkheid. Als een overeenkomstige entiteit wordt gevonden,
		 * wordt de eigenschap van de huidige entiteit bijgewerkt om deze relatie te weerspiegelen.
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
			
			// Als de waarde van de relatiekolom 0 is, wordt de operatie niet voortgezet.
			if ($relationColumnValue === 0) {
				return;
			}
			
			// Bepaal de naam en eigenschap van de doelentiteit op basis van de afhankelijkheid.
			$targetEntityName = $dependency->getTargetEntity();
			$inversedPropertyName = $this->getInversedPropertyName($dependency);
			
			// Voeg de namespace toe aan de naam van de doelentiteit en zoek de gerelateerde entiteit.
			$targetEntity = $this->entityStore->addNamespaceToEntityName($targetEntityName);
			$relationEntity = $this->unitOfWork->findEntity($targetEntity, [$inversedPropertyName => $relationColumnValue]);
			
			// Als een gerelateerde entiteit wordt gevonden, update dan de eigenschap van de huidige entiteit.
			$setterMethod = 'set' . ucfirst($property);

			if (($relationEntity !== null) && method_exists($entity, $setterMethod)) {
				$entity->{$setterMethod}($relationEntity);
			}
		}
		
		/**
		 * Bepaalt de juiste eigenschapnaam voor de inverserelatie op basis van het type afhankelijkheid.
		 * @param object $dependency De afhankelijkheid (OneToOne of ManyToOne).
		 * @return string De naam van de inverserelatie eigenschap.
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
		 * @param array $row De rij met entiteiten om te verwerken.
		 */
		private function setRelations(array $row): void {
			foreach ($row as $value) {
				// Sla deze iteratie over als de waarde geen object is.
				if (!is_object($value)) {
					continue;
				}
				
				// Combineer beide afhankelijkheden in één array voor verwerking.
				$dependencies = array_merge(
					$this->entityStore->getOneToOneDependencies($value),
					$this->entityStore->getManyToOneDependencies($value)
				);
				
				// Sla over als er geen afhankelijkheden zijn.
				if (empty($dependencies)) {
					continue;
				}
				
				// Verwerk elke afhankelijkheid voor de huidige entiteit.
				foreach ($dependencies as $property => $dependency) {
					$this->processEntityDependency($value, $property, $dependency);
				}
			}
		}
		
		/**
		 * Vervangt lege relaties in een reeks entiteiten met proxy-objecten.
		 * Dit is bedoeld om lazy loading van gerelateerde entiteiten te ondersteunen door
		 * een proxy-object te gebruiken in plaats van een null waarde voor niet-geladen relaties.
		 * Zowel ManyToOne als OneToOne relaties worden ondersteund.
		 * @param array $row De rij met entiteiten om te verwerken.
		 */
		private function promoteEmptyRelations(array $row): void {
			foreach ($row as $entity) {
				// Controleer of de huidige waarde een object is. Zo niet, sla dan over.
				if (!is_object($entity)) {
					continue;
				}
				
				// Verkrijg en combineer ManyToOne en OneToOne dependencies voor de huidige entiteit.
				$dependencies = array_merge(
					$this->entityStore->getManyToOneDependencies($entity),
					$this->entityStore->getOneToOneDependencies($entity)
				);
				
				// Als er geen dependencies zijn, ga dan verder naar de volgende entiteit.
				if (empty($dependencies)) {
					continue;
				}
				
				foreach ($dependencies as $property => $dependency) {
					// Haal de huidige waarde van de property op. Als deze niet null is, sla over.
					$propertyValue = $this->propertyHandler->get($entity, $property);

					if ($propertyValue !== null) {
						continue;
					}
					
					// Haal de waarde op van de kolom die de relatie definieert.
					// Als de relatiekolomwaarde 0 is, sla dan deze dependency over.
					$relationColumnValue = $this->propertyHandler->get($entity, $dependency->getRelationColumn());

					if ($relationColumnValue === 0) {
						continue;
					}
					
					// Bepaal de naam van de doelentiteit en de proxy class naam.
					$targetEntityName = $dependency->getTargetEntity();
					$proxyName = $this->getProxyEntityName($targetEntityName);
					
					// Bepaal de naam van de property voor de relatie, afhankelijk van het type dependency.
					if ($dependency instanceof ManyToOne) {
						$relationPropertyName = $dependency->getInversedBy();
					} else {
						$relationPropertyName = $this->determineRelationPropertyName($dependency);
					}
					
					// Creëer een proxy-object en stel de relatie property in.
					$proxy = new $proxyName($this->entityManager);
					$this->propertyHandler->set($proxy, $relationPropertyName, $relationColumnValue);
					$this->propertyHandler->set($entity, $property, $proxy);
					
					// Persist de proxy zodat deze nu beheerd wordt
					$this->entityManager->persist($proxy);
				}
			}
		}

		/**
		 * Loops through all values retrieved from the relationBuffer and promotes
		 * empty collections to lazy loaded collections.
		 */
		private function promoteEmptyOneToMany(array $row): void {
			// Doorloop alle opgehaalde waarden in relationBuffer.
			foreach ($row as $value) {
				if (!is_object($value)) {
					continue;
				}
				
				// Haal de OneToMany afhankelijkheden op voor de gegeven entiteit.
				// Dit identificeert de relaties waarbij één entiteit gekoppeld is aan vele andere.
				$oneToManyDependencies = $this->entityStore->getOneToManyDependencies($value);
				
				// Als er geen OneToMany afhankelijkheden zijn, sla dan deze entiteit over.
				if (empty($oneToManyDependencies)) {
					continue;
				}
				
				// Doorloop alle OneToMany afhankelijkheden.
				foreach ($oneToManyDependencies as $property => $oneToManyDependency) {
					// Voeg de namespace toe aan de naam van de doelentiteit.
					$targetEntity = $this->entityStore->addNamespaceToEntityName($oneToManyDependency->getTargetEntity());
					
					// Haal de huidige waarde van de eigenschap op.
					$propertyValue = $this->propertyHandler->get($value, $property);
					
					// Controleer of de huidige waarde een lege Collection is.
					if (($propertyValue instanceof Collection) && $propertyValue->isEmpty()) {
						// Haal de waarde op van de primary key
						$primaryKeyValue = $this->propertyHandler->get($value, $oneToManyDependency->getRelationColumn());
						
						// Maak een proxy voor de entiteit collectie.
						$proxy = new EntityCollection($this->entityManager, $targetEntity, $oneToManyDependency->getMappedBy(), $primaryKeyValue);
						
						// Stel de proxy in als de nieuwe waarde van de eigenschap.
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
	}