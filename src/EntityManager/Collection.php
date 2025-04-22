<?php
	
	namespace Quellabs\ObjectQuel\EntityManager;
	
	/**
     * Een generieke collectie-klasse
	 * @template T of object
	 * @implements CollectionInterface<T>
	 */
	class Collection implements CollectionInterface {
        
        /**
         * De collectie van objecten, waarbij de sleutel een string of integer kan zijn.
         * @var array<string|int, T>
         */
        protected array $collection;
        
        /**
         * Een array van gesorteerde sleutels, indien aanwezig.
         * @var array<string|int>|null
         */
        protected ?array $sortedKeys = null;
        
        /**
         * Huidige positie in de iteratie van de collectie.
         * @var int|null
         */
        protected ?int $position;
        
        /**
         * Geeft de sorteer volgorde aan als string.
         * @var string
         */
        protected string $sortOrder;
        
        /**
         * Vlag die aangeeft of de collectie is gewijzigd en moet worden hergesorteerd.
         * @var bool
         */
        protected bool $isDirty = false;
        
        /**
         * Collection constructor
         * @param string $sortOrder De sorteer volgorde voor de collectie, standaard een lege string.
         */
        public function __construct(string $sortOrder = '') {
            $this->collection = []; // Initialisatie van de collectie array
            $this->sortOrder = $sortOrder; // Initialisatie van de sorteer volgorde
            $this->position = null; // Initialisatie van de positie
            $this->isDirty = false; // De collectie is nog niet gemarkeerd als gewijzigd
        }
        
        /**
         * Sorteer callback op basis van de sortOrder string
         * Deze functie wordt gebruikt om twee elementen van de collectie met elkaar te vergelijken
         * @param mixed $a Het eerste te vergelijken element
         * @param mixed $b Het tweede te vergelijken element
         * @return int Een integer die aangeeft of $a minder dan, gelijk aan, of groter dan $b is
         */
        protected function sortCallback(mixed $a, mixed $b): int {
            try {
                $fields = array_map('trim', explode(',', $this->sortOrder));
                
                foreach ($fields as $field) {
                    // Split elk veld in property en richting
                    // Bijvoorbeeld: "naam ASC" wordt ["naam", "ASC"]
                    $parts = array_map('trim', explode(' ', $field));
                    $property = $parts[0];
                    
                    // Bepaal de sorteerrichting: -1 voor DESC, 1 voor ASC (standaard)
                    $direction = isset($parts[1]) && strtolower($parts[1]) === 'desc' ? -1 : 1;
                    
                    // Haal de waarden op voor vergelijking
                    $valueA = $this->extractValue($a, $property);
                    $valueB = $this->extractValue($b, $property);
                    
                    // Als beide waarden null zijn, ga door naar het volgende veld
                    if ($valueA === null && $valueB === null) {
                        continue;
                    }
                    
                    // Null-waarden worden als groter beschouwd in PHP
                    if ($valueA === null) {
                        return $direction;
                    }
                    
                    if ($valueB === null) {
                        return -$direction;
                    }
                    
                    // If both values are strings, use case-insensitive comparison
                    if (is_string($valueA) && is_string($valueB)) {
                        $result = strcasecmp($valueA, $valueB);

                        if ($result > 0) {
                            return $direction;
                        }

                        if ($result < 0) {
                            return -$direction;
                        }
                    } elseif ($valueA > $valueB) {
                        return $direction;
                    } elseif ($valueA < $valueB) {
                        return -$direction;
                    }
                    
                    // Als de waarden gelijk zijn, ga door naar het volgende veld
                }
            } catch (\ReflectionException $e) {
                // Log eventuele reflectie-fouten
                error_log("Reflection error in collection sort");
            }
            
            // Als alle velden gelijk zijn, behoud de originele volgorde
            return 0;
		}
		
		/**
		 * Extract a value from a variable based on the given property
		 * @param mixed $var The variable to extract the value from
		 * @param string $property The name of the property to extract
		 * @return mixed The extracted value, or null if not found
		 */
		protected function extractValue(mixed $var, string $property): mixed {
			// Als $var een array is, probeer de waarde op te halen met de property als key
			if (is_array($var)) {
				return $var[$property] ?? null;
			}
			
			// Als $var een object is, probeer de waarde op verschillende manieren op te halen
			if (is_object($var)) {
				// Controleer op een getter methode (bijv. getName() voor property 'name')
				if (method_exists($var, 'get' . ucfirst($property))) {
					return $var->{'get' . ucfirst($property)}();
				}
				
				// Gebruik reflectie om private/protected properties te benaderen
				try {
					$reflection = new \ReflectionClass($var);
					
					if ($reflection->hasProperty($property)) {
						$prop = $reflection->getProperty($property);
						$prop->setAccessible(true);
						return $prop->getValue($var);
					}
				} catch (\ReflectionException $e) {
					// Log de fout als reflectie mislukt
					error_log("Reflection error in collection sort: " . $e->getMessage());
				}
			}
			
			// Voor scalaire waarden (int, float, string, bool), als
			// de property 'value' is, retourneer de waarde zelf.
			if ($property === 'value' && is_scalar($var)) {
				return $var;
			}
			
			// Als geen van bovenstaande methoden werkt, retourneer null
			return null;
		}
		
        /**
         * Bereken en sorteer de sleutels als dat nodig is.
         * @return void
         */
        protected function calculateSortedKeys(): void {
            // Controleer of de gegevens niet gewijzigd zijn en de sleutels al zijn berekend
            if (!$this->isDirty && $this->sortedKeys !== null) {
                return; // Niets te doen, vroegtijdig terugkeren
            }
            
            // Haal de sleutels op
            $this->sortedKeys = $this->getKeys();
            
            // Sorteer de sleutels indien er een sorteervolgorde is ingesteld
            if (!empty($this->sortOrder)) {
                usort($this->sortedKeys, function($keyA, $keyB) {
                    return $this->sortCallback($this->collection[$keyA], $this->collection[$keyB]);
                });
            }
            
            // Markeer de sleutels als up-to-date
            $this->isDirty = false;
        }
		
		/**
		 * Krijg de gesorteerde sleutels van de collection
		 * @return array<string|int>
		 */
		protected function getSortedKeys(): array {
			$this->calculateSortedKeys();
			return $this->sortedKeys;
		}
		
		/**
		 * Removes all entries from the collection
		 * @return void
		 */
		public function clear(): void {
			$this->collection = [];
			$this->position = null;
		}
		
		/**
		 * Returns true if the given key exists in the collection, false if not
		 * @param string $key
		 * @return bool
		 */
		public function containsKey(string $key): bool {
			return isset($this->collection[$key]);
		}
		
		/**
		 * Returns true if the given value exists in the collection, false if not
		 * @param T $value
		 * @return bool
		 */
		public function contains(mixed $value): bool {
			return in_array($value, $this->collection, true);
		}
		
		/**
		 * Returns true if the collection is empty, false if populated
		 * @return bool
		 */
		public function isEmpty(): bool {
			return empty($this->collection);
		}
		
		/**
		 * Returns the number of items in the collection
		 * @return int
		 */
		public function getCount(): int {
			return count($this->collection);
		}
		
		/**
         * Geeft het huidige element in de collectie terug op basis van de huidige positie.
		 * @return T|null
		 */
		public function current() {
			if ($this->position === null) {
				return null;
			}
			
			$keys = $this->getSortedKeys();
			
			if (!isset($keys[$this->position])) {
				return null;
			}
			
			return $this->collection[$keys[$this->position]];
		}
		
		/**
         * Geeft het eerste element in de collectie terug.
		 * @return T|null Het eerste element in de collectie, of null als de collectie leeg is.
		 */
		public function first() {
			$keys = $this->getSortedKeys();
		
			if (!empty($keys)) {
				return $this->collection[$keys[0]];
			}
			
			return null;
		}
        
        /**
         * Verplaatst de interne pointer naar het volgende element in de collectie en geeft dit element terug.
         * @return void
         */
		public function next(): void {
			if ($this->position !== null) {
				$this->position++;
			}
		}
        
        /**
         * Controleert of een bepaalde sleutel in de collectie bestaat.
         * @param mixed $offset
         * @return bool
         */
		public function offsetExists(mixed $offset): bool {
			return array_key_exists($offset, $this->collection);
		}
		
		/**
         * Haalt een element uit de collectie op basis van de gegeven sleutel.
		 * @param string|int $offset De sleutel waarmee het element in de collectie wordt geïdentificeerd.
		 * @return T|null Het element dat overeenkomt met de gegeven sleutel, of null als de sleutel niet bestaat.
		 */
		public function offsetGet($offset) {
			return $this->collection[$offset] ?? null;
		}
		
		/**
         * Stelt een element in de collectie in op een bepaalde sleutel.
		 * @param mixed $offset
		 * @param T $value
		 */
		public function offsetSet(mixed $offset, mixed $value): void {
			if (is_null($offset)) {
				$this->collection[] = $value;
			} else {
				$this->collection[$offset] = $value;
			}
			
			$this->isDirty = true;
		}
        
        /**
         * Verwijdert een element uit de collectie op basis van de opgegeven sleutel.
         * @param mixed $offset De sleutel van het element dat moet worden verwijderd.
         */
		public function offsetUnset(mixed $offset): void {
			unset($this->collection[$offset]);
			$this->isDirty = true;
		}
        
        /**
         * Geeft de huidige sleutel van het element in de collectie terug.
         * @return mixed De sleutel van het huidige element, of null als de positie niet geldig is.
         */
		public function key(): mixed {
			if ($this->position === null) {
				return null;
			}
			
			$keys = $this->getSortedKeys();
			return $keys[$this->position] ?? null;
		}
        
        /**
         * Controleert of de huidige positie geldig is in de collectie.
         * @return bool True als de huidige positie geldig is, anders false.
         */
		public function valid(): bool {
			if ($this->position === null) {
				return false;
			}
			
			$keys = $this->getSortedKeys();
			return isset($keys[$this->position]);
		}
        
        /**
         * Zorg ervoor dat we gesorteerd zijn voordat we beginnen te itereren
         * @return void
         */
		public function rewind(): void {
			$this->calculateSortedKeys();
			$this->position = empty($this->sortedKeys) ? null : 0;
		}
		
		/**
		 * Returns the number of items in the collection
		 * @return int
		 */
		public function count(): int {
			return $this->getCount();
		}
		
		/**
		 * Returns the collection's keys as an array
		 * @return array<string|int>
		 */
		public function getKeys(): array {
			return array_keys($this->collection);
		}
		
		/**
		 * Adds a new value to the collection
		 * @param T $entity
         * @return void
		 */
		public function add($entity): void {
			$this->collection[] = $entity;
			$this->isDirty = true;
		}
		
		/**
		 * Removes a value from the collection
		 * @param T $entity
         * @return bool
         */
		public function remove($entity): bool {
			$key = array_search($entity, $this->collection, true);
			
			if ($key !== false) {
				unset($this->collection[$key]);
				$this->isDirty = true;
				return true;
			}
			
			return false;
		}
		
		/**
		 * Transforms the collection to a sorted array
		 * @return array<T>
		 */
		public function toArray(): array {
			$result = [];

			foreach ($this->getSortedKeys() as $key) {
				$result[] = $this->collection[$key];
			}

			return $result;
		}
		
		/**
		 * Update de sorteervolgorde
		 * @param string $sortOrder Nieuwe sorteervolgorde
		 */
		public function updateSortOrder(string $sortOrder): void {
            // Sla de nieuwe sorteervolgorde op
            $this->sortOrder = $sortOrder;
            
            // Reset gesorteerde sleutels
			$this->sortedKeys = null;
			
            // Zet dirty flag
            $this->isDirty = true;
		}
	}