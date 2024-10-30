<?php
    
    namespace Services\EntityManager;
    
    class EntityCollection implements \ArrayAccess, \Iterator, \Countable {
        
        protected $entity_manager;
        protected $entity_store;
        protected $property_handler;
        protected $collection;
        protected $target_entity;
        protected $mapped_id;
        protected $id;
        protected $initialized;
        protected $iterator;
        
        /**
         * EntityCollection constructor.
         * @param EntityManager $entityManager
         * @param $targetEntity
         * @param $mappedId
         * @param $id
         */
        public function __construct(EntityManager $entityManager, $targetEntity, $mappedId, $id) {
            $this->entity_manager = $entityManager;
            $this->entity_store = $entityManager->getUnitOfWork()->getEntityStore();
            $this->property_handler = $entityManager->getUnitOfWork()->getPropertyHandler();
            $this->collection = new Collection();
            $this->target_entity = $targetEntity;
            $this->mapped_id = $mappedId;
            $this->id = $id;
            $this->initialized = false;
            $this->iterator = false;
        }
    
        /**
         * Returns all entities in the relations
         */
        private function doInitialize(): void {
            if (!$this->initialized) {
                $entities = $this->entity_manager->findBy($this->target_entity, [$this->mapped_id => $this->id]);
            
                foreach($entities as $entity) {
                    $this->collection[] = $entity;
                }
            
                $this->initialized = true;
            }
        }
    
        /**
         * Removes all entities from the list
         */
        public function clear(): void {
            $this->doInitialize();
            $this->collection->clear();
        }
        
        /**
         * Returns true if the entity is in the collection, false if not
         * @param $entity
         * @return bool
         */
        public function contains($entity): bool {
            $this->doInitialize();
            return $this->collection->containsKey(spl_object_hash($entity));
        }
        
        /**
         * Checks whether the collection is empty (contains no elements).
         * @return bool
         */
        public function isEmpty(): bool {
            return $this->collection->isEmpty();
        }
    
        /**
         * Returns the entity currently pointed to by the internal iterator
         * @return mixed|null
         */
        public function current(): mixed {
            $this->doInitialize();
            return $this->collection->current();
        }
    
        /**
         * Returns number of entities
         * @return int
         */
        public function getCount(): int {
			$this->doInitialize();
            return $this->collection->getCount();
        }

        /**
         * Advances the internal iterator to the next entity and returns that entity.
         * If no items left, this function returns null
         * @return void
		 */
        public function next(): void {
            $this->doInitialize();
            $this->collection->next();
        }
    
        /**
         * Wrapper for [] operator (ArrayAccess)
         * @param mixed $offset
         * @return bool
         */
        public function offsetExists(mixed $offset): bool {
            $this->doInitialize();
            return $this->collection->offsetExists($offset);
        }
    
        /**
         * Wrapper for [] operator (ArrayAccess)
         * @param mixed $offset
         * @return mixed|null
         */
        public function offsetGet(mixed $offset): mixed {
            $this->doInitialize();
            return $this->collection->offsetGet($offset);
        }
    
        /**
         * Wrapper for [] operator (ArrayAccess)
         * @param mixed $offset
         * @param mixed $value
         */
        public function offsetSet(mixed $offset, mixed $value): void {
            $this->doInitialize();
            
            if (is_null($offset)) {
                $this->collection->offsetSet(spl_object_hash($value), $value);
            } else {
                $this->collection->offsetSet($offset, $value);
            }
        }
		
		/**
		 * Wrapper for [] operator (ArrayAccess)
		 * @param mixed $offset
		 * @return void
		 */
        public function offsetUnset(mixed $offset): void {
            $this->doInitialize();
            $this->collection->offsetUnset($offset);
        }
		
		/**
		 * Returns the key of the current element in the collection
		 * @return mixed The key of the current element
		 */
		public function key(): mixed {
			$this->doInitialize();
			return $this->collection->key();
		}
		
		/**
		 * Returns true if the current position of the iterator is valid, false if not
		 * @return bool
		 */
		public function valid(): bool {
			$this->doInitialize();
			return $this->collection->valid();
		}
		
		/**
		 * Rewinds the iterator to the first element in the collection
		 */
		public function rewind(): void {
			$this->doInitialize();
			$this->collection->rewind();
		}
		
		/**
		 * Returns the number of elements in the collection
		 * @return int
		 */
		public function count(): int {
			$this->doInitialize();
			return $this->collection->count();
		}
		
		/**
		 * Returns an array of keys from the collection
		 * @return array
		 */
		public function getKeys(): array {
			$this->doInitialize();
			return $this->collection->getKeys();
		}
		
		/**
		 * Returns true if the object is initialized, false if not.
		 * @return bool
		 */
		public function isInitialized(): bool {
			return $this->initialized;
		}
	}