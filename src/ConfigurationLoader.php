<?php
	
	/**
	 * ObjectQuel - A Sophisticated Object-Relational Mapping (ORM) System
	 *
	 * ObjectQuel is an ORM that brings a fresh approach to database interaction,
	 * featuring a unique query language, a streamlined architecture, and powerful
	 * entity relationship management. It implements the Data Mapper pattern for
	 * clear separation between domain models and underlying database structures.
	 *
	 * @author      Floris van den Berg
	 * @copyright   Copyright (c) 2025 ObjectQuel
	 * @license     MIT
	 * @version     1.0.0
	 * @package     Quellabs\ObjectQuel
	 */
	
	namespace Quellabs\ObjectQuel;
	
	/**
	 * Configuration loader for ObjectQuel CLI
	 */
	class ConfigurationLoader {
		
		/**
		 * Loads configuration for CLI tool
		 * @return Configuration
		 * @throws OrmException
		 */
		public static function loadCliConfiguration(): Configuration {
			// First check if the config path is specified in the environment
			if (isset($_ENV['OBJECTQUEL_CONFIG_PATH']) && file_exists($_ENV['OBJECTQUEL_CONFIG_PATH'])) {
				return self::loadFromFile($_ENV['OBJECTQUEL_CONFIG_PATH']);
			}
			
			// Find project root by looking for composer.json
			$projectRoot = self::findProjectRoot();
			
			// Define config file path
			$configFile = $projectRoot . '/objectquel-cli-config.php';
			
			// Check if config file exists
			if (file_exists($configFile)) {
				return self::loadFromFile($configFile);
			}
			
			// Check alternative locations
			$alternativeLocations = [
				$projectRoot . '/objectquel.php',
				$projectRoot . '/config/objectquel.php',
				$projectRoot . '/config/objectquel-cli.php',
			];
			
			foreach ($alternativeLocations as $location) {
				if (file_exists($location)) {
					return self::loadFromFile($location);
				}
			}
			
			// No configuration found
			throw new OrmException(
				"ObjectQuel CLI configuration file not found. Please create one of the following files:\n" .
				"- {$configFile}\n" .
				implode("\n- ", $alternativeLocations) . "\n" .
				"Or set the OBJECTQUEL_CONFIG_PATH environment variable."
			);
		}
		
		/**
		 * Find project root by looking for composer.json
		 *
		 * @return string
		 */
		public static function findProjectRoot(): string {
			$currentDir = getcwd();
			
			// Go up directories until we find composer.json or reach the filesystem root
			while ($currentDir !== '/' && !file_exists($currentDir . '/composer.json')) {
				$currentDir = dirname($currentDir);
			}
			
			// If we found composer.json, that's our project root
			if (file_exists($currentDir . '/composer.json')) {
				return $currentDir;
			}
			
			// Fallback to current directory if we couldn't find project root
			return getcwd();
		}
		
		/**
		 * Load configuration from a PHP file
		 * @param string $path Path to configuration file
		 * @return Configuration The loaded configuration
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
			if (isset($configArray['entity'])) {
				$entity = $configArray['entity'];
				
				if (isset($entity['path'])) {
					$config->setEntityPath($entity['path']);
				}
				
				if (isset($entity['namespace'])) {
					$config->setEntityNameSpace($entity['namespace']);
				}
			}
			
			// Set proxy-related paths and options
			if (isset($configArray['proxy'])) {
				$proxy = $configArray['proxy'];
				
				if (isset($proxy['dir'])) {
					$config->setProxyDir($proxy['dir']);
				}
				
				if (isset($proxy['namespace'])) {
					$config->setProxyNamespace($proxy['namespace']);
				}
				
				if (isset($proxy['enabled'])) {
					$config->setUseProxies((bool)$proxy['enabled']);
				}
			}
			
			// Set cache-related paths and options
			if (isset($configArray['cache'])) {
				$cache = $configArray['cache'];
				
				if (isset($cache['path'])) {
					$config->setCachePath($cache['path']);
				}
				
				if (isset($cache['annotation_path'])) {
					$config->setAnnotationCachePath($cache['annotation_path']);
				}
				
				if (isset($cache['metadata_enabled'])) {
					$config->setUseMetadataCache((bool)$cache['metadata_enabled']);
				}
				
				if (isset($cache['annotation_enabled'])) {
					$config->setUseAnnotationCache((bool)$cache['annotation_enabled']);
				}
			}
			
			// Set migrations path
			if (isset($configArray['migrations']['path'])) {
				$config->setMigrationsPath($configArray['migrations']['path']);
			}
			
			// Set pagination options
			if (isset($configArray['pagination']['default_window_size'])) {
				$config->setDefaultWindowSize((int)$configArray['pagination']['default_window_size']);
			}
			
			return $config;
		}
	}