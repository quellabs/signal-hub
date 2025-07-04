<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Provider\ProviderDefinition;
	
	/**
	 * Defines the contract for scanner classes that discover service providers
	 */
	interface ScannerInterface {
		
		/**
		 * Scan for service providers using the configured strategy
		 * @return array<ProviderDefinition> Array of provider definitions
		 */
		public function scan(): array;
	}