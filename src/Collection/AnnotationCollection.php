<?php
	
	namespace Quellabs\AnnotationReader\Collection;
	
	/**
	 * Immutable collection class for managing annotations.
	 */
	class AnnotationCollection implements \ArrayAccess, \Countable, \Iterator {
		
		/** @var array Array to store annotation objects */
		private array $annotations = [];
		
		/** @var array Array to store the actual keys separately */
		private array $keys = [];
		
		/** @var int Current position for iterator implementation */
		private int $position = 0;
		
		/**
		 * Constructor to initialize the collection with annotations.
		 * @param array $annotations Array of annotation objects to store
		 */
		public function __construct(array $annotations = []) {
			$this->annotations = $annotations;
			$this->keys = array_keys($annotations);
		}
		
		/**
		 * Check if an offset exists in the collection.
		 * @param mixed $offset The offset to check
		 * @return bool True if offset exists, false otherwise
		 */
		public function offsetExists(mixed $offset): bool {
			return isset($this->annotations[$offset]);
		}
		
		/**
		 * Get the value at the specified offset (returns the first annotation of that type).
		 * @param mixed $offset The annotation type to retrieve
		 * @return mixed The first annotation of that type or null if not found
		 */
		public function offsetGet(mixed $offset): mixed {
			$annotations = $this->annotations[$offset] ?? null;
			
			if ($annotations === null) {
				return null;
			}
			
			// If it's an array, return the first element
			if (is_array($annotations)) {
				return $annotations[0] ?? null;
			}
			
			// If it's a single annotation, return it
			return $annotations;
		}
		
		/**
		 * Prevent setting values - collection is immutable.
		 * @param mixed $offset The offset to set
		 * @param mixed $value The value to set
		 * @throws \BadMethodCallException Always thrown as collection is immutable
		 */
		public function offsetSet(mixed $offset, mixed $value): void {
			throw new \BadMethodCallException('AnnotationCollection is immutable');
		}
		
		/**
		 * Prevent unsetting values - collection is immutable.
		 * @param mixed $offset The offset to unset
		 * @throws \BadMethodCallException Always thrown as collection is immutable
		 */
		public function offsetUnset(mixed $offset): void {
			throw new \BadMethodCallException('AnnotationCollection is immutable');
		}
		
		/**
		 * Get the number of annotations in the collection.
		 * @return int The count of annotations
		 */
		public function count(): int {
			return count($this->annotations);
		}
		
		/**
		 * Get the current annotation during iteration.
		 * @return mixed The current annotation or null if position is invalid
		 */
		public function current(): mixed {
			$key = $this->keys[$this->position] ?? null;
			return $key !== null ? $this->annotations[$key] : null;
		}
		
		/**
		 * Get the current key/position during iteration.
		 * @return mixed The current key
		 */
		public function key(): mixed {
			return $this->keys[$this->position] ?? null;
		}
		
		/**
		 * Move to the next position during iteration.
		 * @return void
		 */
		public function next(): void {
			++$this->position;
		}
		
		/**
		 * Reset iterator position to the beginning.
		 * @return void
		 */
		public function rewind(): void {
			$this->position = 0;
		}
		
		/**
		 * Check if the current iterator position is valid.
		 * @return bool True if current position has a valid annotation
		 */
		public function valid(): bool {
			return isset($this->keys[$this->position]);
		}
		
		/**
		 * Get the first annotation in the collection.
		 * @return mixed The first annotation or null if collection is empty
		 */
		public function first(): mixed {
			$firstKey = $this->keys[0] ?? null;
			return $firstKey !== null ? $this->annotations[$firstKey] : null;
		}
		
		/**
		 * Get the last annotation in the collection.
		 * @return mixed The last annotation or null if collection is empty
		 */
		public function last(): mixed {
			$lastKey = end($this->keys);
			return $lastKey !== false ? $this->annotations[$lastKey] : null;
		}
		
		/**
		 * Returns the first key
		 * @return mixed
		 */
		public function getFirstKey(): mixed {
			return $this->keys[0] ?? null;
		}
		
		/**
		 * Check if the collection is empty.
		 * @return bool True if the collection contains no annotations
		 */
		public function isEmpty(): bool {
			return empty($this->annotations);
		}
		
		/**
		 * Convert the collection to a plain array.
		 * @return array The underlying annotations array
		 */
		public function toArray(): array {
			return $this->annotations;
		}
		
		/**
		 * Filter annotations based on a callback function.
		 * @param callable $callback Function to test each annotation
		 * @return self New filtered collection
		 */
		public function filter(callable $callback): self {
			$filtered = [];
			
			foreach ($this->annotations as $annotations) {
				foreach ($annotations as $annotation) {
					if ($callback($annotation)) {
						$filtered[] = $annotation;
					}
				}
			}
			
			return new self($filtered);
		}
		
		/**
		 * Check if an annotation type has multiple instances.
		 * @param string $offset The annotation type to check
		 * @return bool True if there are multiple annotations of this type
		 */
		public function hasMultiple(mixed $offset): bool {
			$annotations = $this->annotations[$offset] ?? null;
			return is_array($annotations) && count($annotations) > 1;
		}
		
		/**
		 * Get all annotations of a specific type.
		 * @param string $type The annotation type
		 * @return array Array of annotations of the specified type
		 */
		public function getAllOfType(string $type): array {
			$annotations = $this->annotations[$type] ?? null;
			
			if ($annotations === null) {
				return [];
			}
			
			return is_array($annotations) ? $annotations : [$annotations];
		}
		
		/**
		 * Merge this AnnotationCollection with another
		 * @param AnnotationCollection $other
		 * @return self
		 */
		public function merge(AnnotationCollection $other): self {
			return new self(array_merge($this->annotations, $other->toArray()));
		}
	}