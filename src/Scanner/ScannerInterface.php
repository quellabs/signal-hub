<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	
	/**
	 * Defines the contract for scanner classes that discover service providers
	 */
	interface ScannerInterface {
		
		/**
		 * Scan for service providers using the configured strategy
		 * @param DiscoveryConfig $config Configuration for the discovery process
		 * @return array<ProviderInterface> Array of instantiated provider objects
		 */
		public function scan(DiscoveryConfig $config): array;
	}