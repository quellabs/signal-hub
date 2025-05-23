<?php
	
	namespace Quellabs\Canvas\Discover;
	
	use Quellabs\DependencyInjection\Provider\ServiceProvider;
	use Quellabs\ObjectQuel\Configuration;
	
	/**
	 * Service provider for ObjectQuel EntityManager
	 */
	class ObjectQuelServiceProvider extends ServiceProvider {
		
		/**
		 * Determines if this provider can create instances of the given class
		 * @param string $className The fully qualified class name to check
		 * @param array $metadata Metadata for filtering
		 * @return bool True if this provider supports the EntityManager class
		 */
		public function supports(string $className, array $metadata): bool {
			return $className === 'Quellabs\\ObjectQuel\\EntityManager';
		}
		
		/**
		 * Creates a new EntityManager instance with proper configuration
		 * @param string $className The class name to instantiate (EntityManager)
		 * @param array $dependencies Additional autowired dependencies (currently unused)
		 * @return object A configured EntityManager instance
		 */
		public function createInstance(string $className, array $dependencies): object {
			return new $className($this->createConfiguration());
		}
		
		/**
		 * Creates and configures the ObjectQuel Configuration object
		 * @return Configuration Fully configured Configuration instance
		 */
		private function createConfiguration(): Configuration {
			// Get database configuration from external config file
			$configData = $this->getConfig();
			
			// Create new configuration instance
			$config = new Configuration();
			
			// Set file system paths for ORM components
			$config->setEntityPath(__DIR__ . '/../Entity');         // Directory containing entity classes
			$config->setProxyDir(__DIR__ . '/../Proxies');       // Directory for generated proxy classes
			
			// Configure metadata caching for improved performance
			$config->setUseMetadataCache(true);                          // Enable metadata caching
			$config->setMetadataCachePath(__DIR__ . '/../Cache/Annotations'); // Cache storage directory
			
			// Configure database connection parameters with fallback defaults
			$config->setDatabaseParams(
				$configData['driver'] ?? 'mysql',               // Database driver (MySQL by default)
				$configData['host'] ?? 'localhost',               // Database host
				$configData['database'] ?? 'motorsportparts', // Database name
				$configData['username'] ?? 'root',          // Database username
				$configData['password'] ?? 'root',          // Database password
				$configData['port'] ?? 3306,                // Database port (standard MySQL port)
				$configData['encoding'] ?? 'utf8mb4'        // Character encoding (supports full Unicode)
			);
			
			return $config;
		}
	}