<?php
	
	namespace Quellabs\Sculpt\Contracts;
	
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\Sculpt\Application;
	
	interface ServiceProviderInterface extends ProviderInterface {

		/**
		 * Bootstrap any application services
		 * @param Application $app
		 */
		public function boot(Application $app): void;
	}