<?php
    
    namespace Services\EntityManager;
    
    class Collection implements \ArrayAccess, \Iterator, \Countable {
        
        protected $collection;
        protected $iterator;
        
        /**
         * Collection constructor.
         */
        public function __construct() {
            $this->collection = [];
            $this->iterator = null;
        }
    
        /**
         * Removes an entity from the list
         */
        public function clear(): void {
            $this->collection = [];
        }
        
        /**
         * Returns true if the key is in the collection, false if not
         * @param string $key
         * @return bool
         */
        public function containsKey(string $key): bool {
            return isset($this->collection[$key]);
        }
        
        /**
         * Returns true if the entity is in the collection, false if not
         * @param mixed $value
         * @return bool
         */
        public function contains(mixed $value): bool {
            return in_array($value, $this->collection);
        }
        
        /**
         * Checks whether the collection is empty (contains no elements).
         * @return bool
         */
        public function isEmpty(): bool {
            return empty($this->collection);
        }
    
        /**
         * Returns number of entities
         * @return int
		 */
        public function getCount(): int {
            return count($this->collection);
        }
		
		/**
		 * Returns the current iterator
		 * @return mixed
		 */
        public function current(): mixed {
			if (is_null($this->iterator)) {
				return null;
			}
			
            return $this->collection[$this->iterator];
        }
		
		/**
		 * Spring naar de eerste iteratie
		 * @return mixed|void|null
		 */
        public function first() {
            if (!empty($this->collection)) {
                $keys = array_keys($this->collection);
                $this->iterator = $keys[0];
                return $this->current();
            }
            
            return null;
        }
		
		/**
		 * Spring naar de volgende iteratie
		 * @return void
		 */
		public function next(): void {
			if (!empty($this->collection)) {
				$keys = array_keys($this->collection);
				$index = array_search($this->iterator, $keys);
				
				if ($index !== false) {
					if ($index < count($keys) - 1) {
						$this->iterator = $keys[$index + 1];
						return;
					}
					
					// Aan het einde van de array, zet de iterator op null
					$this->iterator = null;
				}
			}
		}
    
        public function offsetExists(mixed $offset): bool {
            return array_key_exists($offset, $this->collection);
        }
    
        public function offsetGet(mixed $offset): mixed {
            return $this->collection[$offset] ?? null;
        }
    
        public function offsetSet(mixed $offset, mixed $value): void {
            if (is_null($offset)) {
                $this->collection[] = $value;
            } else {
                $this->collection[$offset] = $value;
            }
        }
    
        public function offsetUnset($offset): void {
            unset($this->collection[$offset]);
        }
		
		public function key(): mixed {
			return $this->iterator;
		}
		
		public function valid(): bool {
			return $this->offsetExists($this->iterator);
		}
		
		public function rewind(): void {
			if (empty($this->collection)) {
				$this->iterator = null;
				return;
			}
			
			$keys = array_keys($this->collection);
			$this->iterator = $keys[0];
		}
		
		public function count(): int {
			return $this->getCount();
		}
		
		function getKeys(): array {
			return array_keys($this->collection);
		}
	}