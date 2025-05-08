<?php
	
	namespace Quellabs\ObjectQuel\Configuration\FrameworkLoaders;
	
	use Quellabs\ObjectQuel\Configuration;
	
	/**
	 * Configuration loader for CakePhp framework
	 */
	class CakePhpLoader implements FrameworkLoaderInterface {
		
		public static function detect(string $projectRoot): bool {
			return false;
		}
		
		public static function load(string $projectRoot): ?Configuration {
			return null;
		}
		
		public static function applyPaths(Configuration $config, string $projectRoot): void {
			// TODO: Implement applyPaths() method.
		}
	}