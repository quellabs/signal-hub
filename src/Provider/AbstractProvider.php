<?php
	
	namespace Quellabs\Discover\Provider;
	
	/**
	 * This class provides common functionality for service providers,
	 * making it easier to implement new providers.
	 */
	abstract class AbstractProvider implements ProviderInterface {

		/**
		 * Services provided by this provider
		 * @var array<string>
		 */
		protected array $provides = [];
		
		/**
		 * Whether this provider has been registered
		 *
		 * @var bool
		 */
		protected bool $registered = false;
		
		/**
		 * Register the provider with a container
		 *
		 * This method is called when the provider is discovered.
		 * It should register all services with the container.
		 * @param mixed $container The service container
		 * @return void
		 */
		public function register(mixed $container): void {
			if ($this->registered) {
				return;
			}
			
			$this->registerServices($container);
			$this->registered = true;
		}
		
		/**
		 * Register services with the container
		 *
		 * This method should be implemented by provider classes
		 * to register their specific services.
		 * @param mixed $container The service container
		 * @return void
		 */
		abstract protected function registerServices(mixed $container): void;
		
		/**
		 * Returns an array of service names or class names that
		 * this provider makes available.
		 * @return array<string> Array of service names or class names
		 */
		public function provides(): array {
			return $this->provides;
		}
		
		/**
		 * Add a service to the list of provided services
		 * @param string $service Service name or class name
		 * @return self
		 */
		protected function addService(string $service): self {
			if (!in_array($service, $this->provides)) {
				$this->provides[] = $service;
			}
			
			return $this;
		}
		
		/**
		 * Add multiple services to the list of provided services
		 * @param array<string> $services Array of service names or class names
		 * @return self
		 */
		protected function addServices(array $services): self {
			foreach ($services as $service) {
				$this->addService($service);
			}
			
			return $this;
		}
		
		/**
		 * Check if the provider provides a specific service
		 * @param string $service Service name or class name
		 * @return bool
		 */
		public function hasService(string $service): bool {
			return in_array($service, $this->provides);
		}
		
		/**
		 * Check if the provider has been registered
		 * @return bool
		 */
		public function isRegistered(): bool {
			return $this->registered;
		}
		
		/**
		 * This method can be overridden to conditionally load providers
		 * based on runtime conditions.
		 * @return bool
		 */
		public function shouldLoad(): bool {
			return true;
		}
	}