<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Provider\ProviderInterface;
	
	/**
	 * Defines the contract for scanner classes that discover service providers
	 */
	interface ScannerInterface {
		
		/**
		 * Scan for service providers using the configured strategy
		 *
		 * Each scanner implementation will use a different approach to find
		 * provider classes, such as scanning composer.json, looking for classes
		 * with specific attributes, or examining specific directories.
		 *
		 * @param DiscoveryConfig $config Configuration for the discovery process
		 * @return array<ProviderInterface> Array of instantiated provider objects
		 */
		public function scan(DiscoveryConfig $config): array;
	}