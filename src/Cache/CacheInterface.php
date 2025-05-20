<?php
	
	namespace Quellabs\Discover\Cache;
	
	/**
	 * This interface defines the standard methods that any cache implementation
	 * within the Quellabs Discover system must implement.
	 */
	interface CacheInterface {
		
		/**
		 * Checks if an item exists in the cache
		 * @param string $key The unique cache key
		 * @return bool True if item exists, false otherwise
		 */
		public function has(string $key): bool;
		
		/**
		 * Retrieves an item from the cache
		 * @param string $key The unique cache key
		 * @param mixed|null $default Value to return if key doesn't exist
		 * @return mixed The cached value or default
		 */
		public function get(string $key, mixed $default = null): mixed;
		
		/**
		 * Stores an item in the cache
		 * @param string $key The unique cache key
		 * @param mixed $value The value to cache
		 * @param int|null $ttl Time to live in seconds, null for infinite
		 * @return bool True on success, false on failure
		 */
		public function set(string $key, mixed $value, ?int $ttl = null): bool;
		
		/**
		 * Removes an item from the cache
		 * @param string $key The unique cache key to remove
		 * @return bool True if removed or not found, false on failure
		 */
		public function forget(string $key): bool;
	}