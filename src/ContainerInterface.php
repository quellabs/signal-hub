<?php
	
	namespace Quellabs\DependencyInjection\Container;
	
	/**
	 * Core container interface
	 */
	interface ContainerInterface {
		
		/**
		 * Get a service by its ID
		 * @template T
		 * @param class-string<T> $className Class or interface name
		 * @return T|null
		 */
		public function get(string $className): ?object;
	}