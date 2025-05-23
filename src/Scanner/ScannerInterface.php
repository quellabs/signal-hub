<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Contracts\Discovery\ProviderInterface;
	
	/**
	 * Defines the contract for scanner classes that discover service providers
	 */
	interface ScannerInterface {
		
		/**
		 * Scan for service providers using the configured strategy
		 * @return array<ProviderInterface> Array of instantiated provider objects
		 */
		public function scan(): array;
	}