<?php
	
	namespace Quellabs\ObjectQuel\Kernel;
	
	interface ServiceInterface {
		
		/**
		 * Checks if the Service supports the given class
		 * @param class-string $class
		 * @return bool
		 */
		public function supports(string $class): bool;
		
		/**
		 * Returns an instance of the requested class
		 * @param class-string $class
		 * @param array<string, mixed> $parameters Currently unused, but kept for interface compatibility
		 * @return object|null The requested instance or null if class is not supported
		 */
		public function getInstance(string $class, array $parameters = []): ?object;
	}