<?php
	
	namespace Quellabs\ObjectQuel\Configuration\FrameworkLoaders;
	
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\Configuration\ConfigurationDiscovery;
	
	/**
	 * Configuration loader for Symfony framework
	 */
	class SymfonyLoader implements FrameworkLoaderInterface {
		
		/**
		 * Detect if Symfony is being used in the current project
		 * @param string $projectRoot The project root directory
		 * @return bool True if Symfony is detected, false otherwise
		 */
		public static function detect(string $projectRoot): bool {
			return
				file_exists($projectRoot . '/bin/console') &&
				file_exists($projectRoot . '/config/bundles.php');
		}
		
		/**
		 * Load configuration from Symfony
		 * @param string $projectRoot The project root directory
		 * @return Configuration|null Configuration object or null if Symfony config couldn't be loaded
		 */
		public static function load(string $projectRoot): ?Configuration {
			try {
				// Check for dedicated ObjectQuel config in Symfony packages
				$objectQuelConfigPath = $projectRoot . '/config/packages/objectquel.yaml';
				$objectQuelConfigPathPhp = $projectRoot . '/config/packages/objectquel.php';
				
				if (file_exists($objectQuelConfigPathPhp)) {
					$objectQuelConfig = require $objectQuelConfigPathPhp;
					
					if (is_array($objectQuelConfig)) {
						return self::createFromArray($objectQuelConfig);
					}
				}
				
				if (file_exists($objectQuelConfigPath) && extension_loaded('yaml')) {
					$objectQuelConfig = yaml_parse_file($objectQuelConfigPath);
					
					if (is_array($objectQuelConfig) && isset($objectQuelConfig['objectquel'])) {
						return self::createFromArray($objectQuelConfig['objectquel']);
					}
				}
				
				// No valid configuration found in Symfony's config directory
				return null;
			} catch (\Throwable $e) {
				// If any error occurs during config loading, return null
				error_log('ObjectQuel configuration loading error: ' . $e->getMessage());
				return null;
			}
		}
		
		/**
		 * Apply Symfony-specific paths to an existing configuration
		 * @param Configuration $config The configuration to update with Symfony-specific paths
		 * @param string $projectRoot The project root directory
		 * @return void
		 */
		public static function applyPaths(Configuration $config, string $projectRoot): void {
			// Set Symfony standard paths
			$srcDir = $projectRoot . '/src';
			$varDir = $projectRoot . '/var';
			
			$config->setEntityPath($srcDir . '/Entity');
			$config->setEntityNameSpace('App\\Entity');
			$config->setProxyDir($varDir . '/cache/objectquel/proxies');
			$config->setProxyNamespace('Proxies\\ObjectQuel');
			$config->setCachePath($varDir . '/cache/objectquel');
			$config->setAnnotationCachePath($varDir . '/cache/objectquel/annotations');
			$config->setMigrationsPath($projectRoot . '/migrations/objectquel');
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