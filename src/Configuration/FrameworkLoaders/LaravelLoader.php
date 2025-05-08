<?php
	
	namespace Quellabs\ObjectQuel\Configuration\FrameworkLoaders;
	
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\Configuration\ConfigurationDiscovery;
	
	/**
	 * Configuration loader for Laravel framework
	 */
	class LaravelLoader implements FrameworkLoaderInterface {
		
		/**
		 * Detect if Laravel is being used in the current project
		 * @param string $projectRoot The project root directory
		 * @return bool True if Laravel is detected, false otherwise
		 */
		public static function detect(string $projectRoot): bool {
			return
				file_exists($projectRoot . '/artisan') &&
				file_exists($projectRoot . '/bootstrap/app.php');
		}
		
		/**
		 * Load configuration from Laravel
		 * @param string $projectRoot The project root directory
		 * @return Configuration|null Configuration object or null if Laravel config couldn't be loaded
		 */
		public static function load(string $projectRoot): ?Configuration {
			// Check if Laravel's app.php is available
			if (!file_exists($projectRoot . '/bootstrap/app.php')) {
				return null;
			}
			
			try {
				// Load Laravel's application
				require_once $projectRoot . '/bootstrap/app.php';
				
				// Check if Laravel's config system is available
				if (!function_exists('config')) {
					return null;
				}
				
				// Create configuration from Laravel's config
				$config = new Configuration();
				
				// Check if ObjectQuel config exists in Laravel
				if (function_exists('config') && config()->has('objectquel')) {
					$objectQuelConfig = config('objectquel');
					
					if (is_array($objectQuelConfig)) {
						return self::createFromArray($objectQuelConfig);
					}
				}
				
				// If no specific ObjectQuel config, use Laravel's database config
				if (function_exists('config') && config()->has('database.connections')) {
					$defaultConnection = config('database.default');
					$dbConfig = config("database.connections.{$defaultConnection}");
					
					if (is_array($dbConfig)) {
						// Map Laravel DB config to ObjectQuel format
						$driver = $dbConfig['driver'] ?? 'mysql';
						$host = $dbConfig['host'] ?? 'localhost';
						$database = $dbConfig['database'] ?? 'objectquel';
						$username = $dbConfig['username'] ?? 'root';
						$password = $dbConfig['password'] ?? '';
						$port = $dbConfig['port'] ?? 3306;
						$charset = $dbConfig['charset'] ?? 'utf8mb4';
						
						$config->setDatabaseParams(
							$driver, $host, $database, $username, $password, $port, $charset
						);
						
						// Apply Laravel paths to the configuration
						self::applyPaths($config, $projectRoot);
						
						return $config;
					}
				}
				
				// No valid Laravel configuration found
				return null;
			} catch (\Throwable $e) {
				// If any error occurs during Laravel bootstrapping, return null
				error_log('ObjectQuel Laravel configuration loading error: ' . $e->getMessage());
				return null;
			}
		}
		
		/**
		 * Apply Laravel-specific paths to an existing configuration
		 * @param Configuration $config The configuration to update with Laravel-specific paths
		 * @param string $projectRoot The project root directory
		 * @return void
		 */
		public static function applyPaths(Configuration $config, string $projectRoot): void {
			try {
				// If Laravel functions are already loaded, use them to set paths
				if (function_exists('app_path') && function_exists('storage_path') && function_exists('database_path')) {
					$config->setEntityPath(app_path('Models'));
					$config->setEntityNameSpace('App\\Models');
					$config->setProxyDir(storage_path('app/proxies'));
					$config->setProxyNamespace('App\\Proxies');
					$config->setCachePath(storage_path('framework/cache/objectquel'));
					$config->setAnnotationCachePath(storage_path('framework/cache/objectquel/annotations'));
					$config->setMigrationsPath(database_path('objectquel-migrations'));
				} else {
					// Laravel functions not loaded, use direct paths
					$config->setEntityPath($projectRoot . '/app/Models');
					$config->setEntityNameSpace('App\\Models');
					$config->setProxyDir($projectRoot . '/storage/app/proxies');
					$config->setProxyNamespace('App\\Proxies');
					$config->setCachePath($projectRoot . '/storage/framework/cache/objectquel');
					$config->setAnnotationCachePath($projectRoot . '/storage/framework/cache/objectquel/annotations');
					$config->setMigrationsPath($projectRoot . '/database/objectquel-migrations');
				}
			} catch (\Throwable $e) {
				// If any error occurs, use direct paths as fallback
				$config->setEntityPath($projectRoot . '/app/Models');
				$config->setEntityNameSpace('App\\Models');
				$config->setProxyDir($projectRoot . '/storage/app/proxies');
				$config->setProxyNamespace('App\\Proxies');
				$config->setCachePath($projectRoot . '/storage/framework/cache/objectquel');
				$config->setAnnotationCachePath($projectRoot . '/storage/framework/cache/objectquel/annotations');
				$config->setMigrationsPath($projectRoot . '/database/objectquel-migrations');
			}
		}
		
		/**
		 * Helper to create Configuration from array
		 * @param array $configArray Configuration array
		 * @return Configuration
		 */
		private static function createFromArray(array $configArray): Configuration {
			return ConfigurationDiscovery::createFromArray($configArray);
		}
	}