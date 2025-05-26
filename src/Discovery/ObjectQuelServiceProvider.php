<?php
	
	namespace Quellabs\ObjectQuel\Discovery;
	
	use Quellabs\DependencyInjection\Provider\ServiceProvider;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\EntityManager;
	
	/**
	 * Service provider for ObjectQuel EntityManager
	 */
	class ObjectQuelServiceProvider extends ServiceProvider {
		
		/**
		 * Cached singleton instance of the template engine.
		 * @var EntityManager|null
		 */
		private static ?EntityManager $instance = null;
		
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
			// Return existing instance if already created (singleton behavior)
			if (self::$instance !== null) {
				return self::$instance;
			}
			
			// Cache the instance for future requests (singleton pattern)
			self::$instance = new $className($this->createConfiguration());
			
			// Return the singleton instance
			return self::$instance;
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
			
			// Directory containing entity classes
			$config->setEntityPath($configData["entity_path"] ?? __DIR__ . '/../Entity');
			
			// Directory for generated proxy classes
			$config->setProxyDir($configData["proxy_path"] ?? __DIR__ . '/../Proxies');
			
			// Enable metadata caching
			$config->setUseMetadataCache(true);
			
			// Set metadata path
			$config->setMetadataCachePath($configData["metadata_path"] ?? __DIR__ . '/../Cache/Annotations');
			
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