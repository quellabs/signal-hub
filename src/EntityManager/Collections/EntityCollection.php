<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Collections;
	
	use Quellabs\ObjectQuel\EntityManager;
	use Quellabs\ObjectQuel\EntityManager\EntityStore;
	use Quellabs\ObjectQuel\EntityManager\Reflection\PropertyHandler;
	
	/**
	 * @template T
	 * @implements CollectionInterface<T>
	 */
	class EntityCollection implements CollectionInterface {
		
		protected EntityManager $entity_manager;
		protected EntityStore $entity_store;
		protected PropertyHandler $property_handler;
		protected Collection $collection;
		protected mixed $target_entity;
		protected mixed $mapped_id;
		protected mixed $id;
		protected bool $initialized;
		protected mixed $iterator;
		
		/**
		 * EntityCollection constructor.
		 * @param EntityManager $entityManager
		 * @param $targetEntity
		 * @param $mappedId
		 * @param $id
		 */
		public function __construct(EntityManager $entityManager, $targetEntity, $mappedId, $id, string $sortOrder = '') {
			$this->entity_manager = $entityManager;
			$this->entity_store = $entityManager->getUnitOfWork()->getEntityStore();
			$this->property_handler = $entityManager->getUnitOfWork()->getPropertyHandler();
			$this->collection = new Collection($sortOrder);
			$this->target_entity = $targetEntity;
			$this->mapped_id = $mappedId;
			$this->id = $id;
			$this->initialized = false;
			$this->iterator = false;
		}
		
		/**
		 * Initialiseert de collectie met entiteiten.
		 * Deze functie wordt aangeroepen om de collectie te vullen met entiteiten
		 * die overeenkomen met de opgegeven criteria.
		 * @return void
		 */
		private function doInitialize(): void {
			// Controleer of de initialisatie al is uitgevoerd
			if (!$this->initialized) {
				// Markeer als geïnitialiseerd om herhaalde initialisatie te voorkomen
				$this->initialized = true;
				
				// Haal entiteiten op uit de database die overeenkomen met de opgegeven criteria
				$entities = $this->entity_manager->findBy($this->target_entity, [$this->mapped_id => $this->id]);
				
				// Voeg elke gevonden entiteit toe aan de collectie
				foreach ($entities as $entity) {
					if (!$this->contains($entity)) {
						$this->collection[] = $entity;
					}
				}
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
			return $this->collection->containsKey(spl_object_id($entity));
		}
		
		/**
		 * Checks whether the collection is empty (contains no elements).
		 * @return bool
		 */
		public function isEmpty(): bool {
			$this->doInitialize();
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
				$this->collection->offsetSet(spl_object_id($value), $value);
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
		
		/**
		 * @param T $entity
		 * @return void
		 */
		public function add($entity): void {
			$this->doInitialize();
			
			if (!$this->contains($entity)) {
				$this->collection->offsetSet(spl_object_id($entity), $entity);
			}
		}
		
		/**
		 * @param T $entity
		 * @return bool
		 */
		public function remove($entity): bool {
			$this->doInitialize();
			$id = spl_object_id($entity);
			
			if ($this->collection->offsetExists($id)) {
				$this->collection->offsetUnset($id);
				return true;
			}
			
			return false;
		}
		
		/**
		 * @return array<T>
		 */
		public function toArray(): array {
			$this->doInitialize();
			return $this->collection->toArray();
		}
	}