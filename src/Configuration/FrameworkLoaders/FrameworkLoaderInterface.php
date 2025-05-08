<?php
	
	namespace Quellabs\ObjectQuel\Configuration\FrameworkLoaders;
	
	use Quellabs\ObjectQuel\Configuration;
	
	/**
	 * Interface for framework-specific configuration loaders
	 */
	interface FrameworkLoaderInterface {
		
		/**
		 * Detect if this framework is being used in the current project
		 * @param string $projectRoot The project root directory
		 * @return bool True if this framework is detected, false otherwise
		 */
		public static function detect(string $projectRoot): bool;
		
		/**
		 * Load configuration from this framework
		 * @param string $projectRoot The project root directory
		 * @return Configuration|null Configuration object or null if framework config couldn't be loaded
		 */
		public static function load(string $projectRoot): ?Configuration;
		
		/**
		 * Apply framework-specific paths to an existing configuration
		 * @param Configuration $config The configuration to update with framework-specific paths
		 * @param string $projectRoot The project root directory
		 * @return void
		 */
		public static function applyPaths(Configuration $config, string $projectRoot): void;
	}