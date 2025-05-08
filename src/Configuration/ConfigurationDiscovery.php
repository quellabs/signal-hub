<?php
	
	namespace Quellabs\ObjectQuel\Configuration;
	
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\Configuration\FrameworkLoaders\FrameworkLoaderInterface;
	use Quellabs\ObjectQuel\Configuration\FrameworkLoaders\LaravelLoader;
	use Quellabs\ObjectQuel\Configuration\FrameworkLoaders\SymfonyLoader;
	use Quellabs\ObjectQuel\Configuration\FrameworkLoaders\YiiLoader;
	use Quellabs\ObjectQuel\Configuration\FrameworkLoaders\CakePhpLoader;
	use Quellabs\ObjectQuel\OrmException;
	
	/**
	 * Configuration discovery for ObjectQuel ORM
	 * Automatically locates and loads configuration files across different project structures
	 */
	class ConfigurationDiscovery {
		/**
		 * Framework types that can be detected
		 */
		public const FRAMEWORK_LARAVEL = 'laravel';
		public const FRAMEWORK_SYMFONY = 'symfony';
		public const FRAMEWORK_YII = 'yii';
		public const FRAMEWORK_CAKEPHP = 'cakephp';
		public const FRAMEWORK_UNKNOWN = 'unknown';
		
		/**
		 * Available framework loaders
		 */
		private static array $frameworkLoaders = [
			self::FRAMEWORK_LARAVEL => LaravelLoader::class,
			self::FRAMEWORK_SYMFONY => SymfonyLoader::class,
			self::FRAMEWORK_YII     => YiiLoader::class,
			self::FRAMEWORK_CAKEPHP => CakePhpLoader::class,
		];
		
		/**
		 * Possible configuration file locations in order of priority
		 */
		private static array $searchPaths = [
			// Project root configs
			'objectquel.config.php',
			'config/objectquel.php',
			'config/database.php',
		];
		
		/**
		 * Common environment variable names for database configuration
		 */
		private static array $envVariableMappings = [
			'driver'   => ['DATABASE_DRIVER', 'DB_CONNECTION', 'DB_DRIVER'],
			'host'     => ['DATABASE_HOST', 'DB_HOST'],
			'database' => ['DATABASE_NAME', 'DATABASE_DB', 'DB_DATABASE', 'DB_NAME'],
			'username' => ['DATABASE_USER', 'DATABASE_USERNAME', 'DB_USERNAME', 'DB_USER'],
			'password' => ['DATABASE_PASSWORD', 'DB_PASSWORD'],
			'port'     => ['DATABASE_PORT', 'DB_PORT'],
			'charset'  => ['DATABASE_CHARSET', 'DB_CHARSET', 'DATABASE_ENCODING', 'DB_ENCODING'],
		];
		
		/**
		 * Discover configuration from standard locations
		 * @return Configuration|null Returns Configuration object if found, null otherwise
		 * @throws \RuntimeException If no configuration file is found
		 * @throws OrmException
		 */
		public static function discover(): ?Configuration {
			// First, check if the config path is specified in the environment
			if (isset($_ENV['OBJECTQUEL_CONFIG_PATH']) && file_exists($_ENV['OBJECTQUEL_CONFIG_PATH'])) {
				return self::loadFromFile($_ENV['OBJECTQUEL_CONFIG_PATH']);
			}
			
			// Detect the framework and project root
			$frameworkType = self::detectFramework();
			$projectRoot = self::findProjectRoot();
			
			// Try to load from .env files
			$envConfig = self::loadFromDotEnv($projectRoot);
			
			if ($envConfig !== null) {
				if ($frameworkType !== self::FRAMEWORK_UNKNOWN) {
					self::applyFrameworkPaths($envConfig, $frameworkType, $projectRoot);
				}
				
				return $envConfig;
			}
			
			// If no .env file found, but we detected a supported framework, try to load config from it
			if ($frameworkType !== self::FRAMEWORK_UNKNOWN) {
				$frameworkConfig = self::fromFramework($frameworkType);
				
				if ($frameworkConfig !== null) {
					return $frameworkConfig;
				}
			}
			
			// Look through standard paths relative to project root
			foreach (self::$searchPaths as $relativePath) {
				$absolutePath = rtrim($projectRoot, '/') . '/' . $relativePath;
				
				if (file_exists($absolutePath)) {
					return self::loadFromFile($absolutePath);
				}
			}
			
			// No configuration found
			throw new OrmException(
				"ObjectQuel configuration file not found. Please create one in one of the following locations: " .
				implode(', ', array_map(fn($path) => rtrim($projectRoot, '/') . '/' . $path, self::$searchPaths))
			);
		}
		
		/**
		 * Load configuration from .env files
		 * @param string $projectRoot The project root directory
		 * @return Configuration|null Configuration object or null if .env config couldn't be loaded
		 */
		public static function loadFromDotEnv(string $projectRoot): ?Configuration {
			try {
				// Check if a .env file exists
				if (!file_exists($projectRoot . '/.env')) {
					return null;
				}
				
				// Load environment variables from .env files using vlucas/phpdotenv
				$dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
				$dotenv->safeLoad();
				
				// Collect database configuration from environment variables
				$dbConfig = [];
				
				// Iterate through mappings and find values from environment variables
				foreach (self::$envVariableMappings as $configKey => $envVars) {
					foreach ($envVars as $envVar) {
						if (isset($_ENV[$envVar]) && $_ENV[$envVar] !== '') {
							$dbConfig[$configKey] = $_ENV[$envVar];
							break;
						}
						
						// Also check $_SERVER as some environments use it instead
						if (isset($_SERVER[$envVar]) && $_SERVER[$envVar] !== '') {
							$dbConfig[$configKey] = $_SERVER[$envVar];
							break;
						}
					}
				}
				
				// Check if we have minimum required configuration
				$hasDatabaseName = isset($dbConfig['database']);
				$hasDriver = isset($dbConfig['driver']);
				$hasHost = isset($dbConfig['host']);

				if (!$hasDatabaseName || !$hasDriver || $hasHost) {
					return null;
				}
				
				// Set default values for optional parameters
				$driver = $dbConfig['driver'];
				$isPostgres = ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql');
				
				$dbConfig = array_merge([
					'username' => 'root',
					'password' => '',
					'port'     => $isPostgres ? 5432 : 3306,
					'charset'  => 'utf8mb4',
				], $dbConfig);
				
				// Create configuration
				return self::createConfigFromDatabaseParams($dbConfig, $projectRoot);
			} catch (\Throwable $e) {
				// If any error occurs during env loading, return null
				error_log('ObjectQuel .env configuration loading error: ' . $e->getMessage());
				return null;
			}
		}
		
		/**
		 * Create Configuration from database parameters
		 * @param array $dbConfig Database configuration parameters
		 * @param string $projectRoot The project root directory
		 * @return Configuration
		 */
		public static function createConfigFromDatabaseParams(array $dbConfig, string $projectRoot): Configuration {
			$config = new Configuration();
			
			// Map database config to ObjectQuel format
			$driver = $dbConfig['driver'] ?? $dbConfig['type'] ?? 'mysql';
			$host = $dbConfig['host'] ?? 'localhost';
			$dbname = $dbConfig['dbname'] ?? $dbConfig['database'] ?? 'objectquel';
			$user = $dbConfig['user'] ?? $dbConfig['username'] ?? 'root';
			$password = $dbConfig['password'] ?? '';
			$port = $dbConfig['port'] ?? ($driver === 'mysql' ? 3306 : 5432);
			$charset = $dbConfig['charset'] ?? $dbConfig['encoding'] ?? 'utf8mb4';
			
			$config->setDatabaseParams(
				$driver, $host, $dbname, $user, $password, $port, $charset
			);
			
			// Set default paths based on known frameworks or fallback to generic paths
			$frameworkType = self::detectFramework();
			
			switch ($frameworkType) {
				case self::FRAMEWORK_LARAVEL:
					$config->setEntityPath($projectRoot . '/app/Models');
					$config->setEntityNameSpace('App\\Models');
					$config->setProxyDir($projectRoot . '/storage/app/proxies');
					$config->setProxyNamespace('App\\Proxies');
					$config->setCachePath($projectRoot . '/storage/framework/cache/objectquel');
					$config->setAnnotationCachePath($projectRoot . '/storage/framework/cache/objectquel/annotations');
					$config->setMigrationsPath($projectRoot . '/database/objectquel-migrations');
					break;
				
				case self::FRAMEWORK_SYMFONY:
					$config->setEntityPath($projectRoot . '/src/Entity');
					$config->setEntityNameSpace('App\\Entity');
					$config->setProxyDir($projectRoot . '/var/cache/objectquel/proxies');
					$config->setProxyNamespace('Proxies\\ObjectQuel');
					$config->setCachePath($projectRoot . '/var/cache/objectquel');
					$config->setAnnotationCachePath($projectRoot . '/var/cache/objectquel/annotations');
					$config->setMigrationsPath($projectRoot . '/migrations/objectquel');
					break;
				
				default:
					// Generic paths for unknown frameworks
					$config->setEntityPath($projectRoot . '/src/Entity');
					$config->setEntityNameSpace('App\\Entity');
					$config->setProxyDir($projectRoot . '/metadata/proxies');
					$config->setProxyNamespace('Proxies\\ObjectQuel');
					$config->setCachePath($projectRoot . '/metadata/cache');
					$config->setAnnotationCachePath($projectRoot . '/metadata/cache');
					$config->setMigrationsPath($projectRoot . '/migrations');
					break;
			}
			
			return $config;
		}
		
		/**
		 * Detect which framework is being used in the current project
		 * @return string One of the FRAMEWORK_* constants
		 */
		public static function detectFramework(): string {
			$projectRoot = self::findProjectRoot();
			
			// Try each framework loader
			foreach (self::$frameworkLoaders as $frameworkType => $loaderClass) {
				/** @var FrameworkLoaderInterface $loaderClass */
				if ($loaderClass::detect($projectRoot)) {
					return $frameworkType;
				}
			}
			
			// No supported framework detected
			return self::FRAMEWORK_UNKNOWN;
		}
		
		/**
		 * Load configuration from a specific framework
		 * @param string $framework One of the FRAMEWORK_* constants
		 * @return Configuration|null Configuration object or null if framework config couldn't be loaded
		 */
		public static function fromFramework(string $framework): ?Configuration {
			if (!isset(self::$frameworkLoaders[$framework])) {
				return null;
			}
			
			$loaderClass = self::$frameworkLoaders[$framework];
			$projectRoot = self::findProjectRoot();
			
			/** @var FrameworkLoaderInterface $loaderClass */
			return $loaderClass::load($projectRoot);
		}
		
		/**
		 * Find project root by looking for composer.json
		 * @return string
		 */
		private static function findProjectRoot(): string {
			$currentDir = __DIR__;
			
			// Go up directories until we find composer.json or reach the filesystem root
			while ($currentDir !== '/' && !file_exists($currentDir . '/composer.json')) {
				$currentDir = dirname($currentDir);
			}
			
			// If we found composer.json, that's our project root
			if (file_exists($currentDir . '/composer.json')) {
				return $currentDir;
			}
			
			// Fallback to current directory if we couldn't find project root
			return __DIR__;
		}
		
		/**
		 * Load configuration from a PHP file
		 * @param string $path Path to configuration file
		 * @return Configuration The loaded configuration
		 * @throws \RuntimeException If file doesn't return a configuration array
		 * @throws OrmException
		 */
		private static function loadFromFile(string $path): Configuration {
			$configData = require $path;
			
			// Handle different return types from the config file
			switch (true) {
				case $configData instanceof Configuration:
					// Config file directly returns a Configuration object
					return $configData;
				
				case is_array($configData):
					// Config file returns an array, create Configuration from it
					return self::createFromArray($configData);
				
				default:
					throw new OrmException(
						"Configuration file at {$path} must return either a Configuration object or an array of configuration parameters"
					);
			}
		}
		
		/**
		 * Create Configuration object from array
		 * @param array $configArray Configuration parameters
		 * @return Configuration The created configuration
		 */
		public static function createFromArray(array $configArray): Configuration {
			$config = new Configuration();
			
			// Handle database connection parameters
			if (isset($configArray['database'])) {
				$db = $configArray['database'];
				
				// Check if complete DSN is provided
				if (isset($db['dsn'])) {
					$config->setDsn($db['dsn']);
				} elseif (isset($db['driver'], $db['host'], $db['database'], $db['username'], $db['password'])) {
					// Set up from individual parameters
					$config->setDatabaseParams(
						$db['driver'],
						$db['host'],
						$db['database'],
						$db['username'],
						$db['password'],
						$db['port'] ?? 3306,
						$db['encoding'] ?? 'utf8mb4',
						$db['flags'] ?? []
					);
				} elseif (isset($db['driver'], $db['host'], $db['dbname'], $db['user'], $db['password'])) {
					// Doctrine-style naming convention
					$config->setDatabaseParams(
						$db['driver'],
						$db['host'],
						$db['dbname'],
						$db['user'],
						$db['password'],
						$db['port'] ?? 3306,
						$db['charset'] ?? 'utf8mb4',
						$db['options'] ?? []
					);
				}
			}
			
			// Set entity-related paths and namespaces
			if (isset($configArray['entity_path'])) {
				$config->setEntityPath($configArray['entity_path']);
			} elseif (isset($configArray['entity_paths'])) {
				$config->setEntityPath($configArray['entity_paths']);
			} elseif (isset($configArray['entities_path'])) {
				$config->setEntityPath($configArray['entities_path']);
			}
			
			if (isset($configArray['entity_namespace'])) {
				$config->setEntityNameSpace($configArray['entity_namespace']);
			} elseif (isset($configArray['entity_namespace'])) {
				$config->setEntityNameSpace($configArray['entity_namespace']);
			} elseif (isset($configArray['entities_namespace'])) {
				$config->setEntityNameSpace($configArray['entities_namespace']);
			}
			
			// Set proxy-related paths and options
			if (isset($configArray['proxy_dir'])) {
				$config->setProxyDir($configArray['proxy_dir']);
			} elseif (isset($configArray['proxies_dir'])) {
				$config->setProxyDir($configArray['proxies_dir']);
			}
			
			if (isset($configArray['proxy_namespace'])) {
				$config->setProxyNamespace($configArray['proxy_namespace']);
			} elseif (isset($configArray['proxies_namespace'])) {
				$config->setProxyNamespace($configArray['proxies_namespace']);
			}
			
			if (isset($configArray['use_proxies'])) {
				$config->setUseProxies((bool)$configArray['use_proxies']);
			}
			
			// Set cache-related paths and options
			if (isset($configArray['cache_path'])) {
				$config->setCachePath($configArray['cache_path']);
			} elseif (isset($configArray['cache_dir'])) {
				$config->setCachePath($configArray['cache_dir']);
			}
			
			if (isset($configArray['use_metadata_cache'])) {
				$config->setUseMetadataCache((bool)$configArray['use_metadata_cache']);
			} elseif (isset($configArray['metadata_cache'])) {
				$config->setUseMetadataCache((bool)$configArray['metadata_cache']);
			}
			
			// Set annotation cache options
			if (isset($configArray['annotation_cache_path'])) {
				$config->setAnnotationCachePath($configArray['annotation_cache_path']);
			} elseif (isset($configArray['annotation_cache_dir'])) {
				$config->setAnnotationCachePath($configArray['annotation_cache_dir']);
			}
			
			if (isset($configArray['use_annotation_cache'])) {
				$config->setUseAnnotationCache((bool)$configArray['use_annotation_cache']);
			} elseif (isset($configArray['annotation_cache'])) {
				$config->setUseAnnotationCache((bool)$configArray['annotation_cache']);
			}
			
			// Set migrations path
			if (isset($configArray['migrations_path'])) {
				$config->setMigrationsPath($configArray['migrations_path']);
			} elseif (isset($configArray['migration_path'])) {
				$config->setMigrationsPath($configArray['migration_path']);
			} elseif (isset($configArray['migrations_dir'])) {
				$config->setMigrationsPath($configArray['migrations_dir']);
			}
			
			// Set pagination options
			if (isset($configArray['default_window_size'])) {
				$config->setDefaultWindowSize((int)$configArray['default_window_size']);
			} elseif (isset($configArray['window_size'])) {
				$config->setDefaultWindowSize((int)$configArray['window_size']);
			} elseif (isset($configArray['page_size'])) {
				$config->setDefaultWindowSize((int)$configArray['page_size']);
			}
			
			return $config;
		}
		
		/**
		 * Apply framework-specific paths to a configuration
		 * @param Configuration $config The configuration to update
		 * @param string $framework One of the FRAMEWORK_* constants
		 * @param string $projectRoot The project root directory
		 * @return void
		 */
		private static function applyFrameworkPaths(Configuration $config, string $framework, string $projectRoot): void {
			if (!isset(self::$frameworkLoaders[$framework])) {
				return;
			}
			
			$loaderClass = self::$frameworkLoaders[$framework];
			$loaderClass::applyPaths($config, $projectRoot);
		}
	}