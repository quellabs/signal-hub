<?php
	
	namespace Quellabs\Contracts\DependencyInjection;
	
	/**
	 * Core container interface
	 */
	interface Container {
		
		/**
		 * Get a service by its ID
		 * @template T
		 * @param class-string<T> $className Class or interface name
		 * @return T|null
		 */
		public function get(string $className): ?object;
	}