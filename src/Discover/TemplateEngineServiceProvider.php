<?php
	
	namespace Quellabs\Canvas\Discover;
	
	use Quellabs\Contracts\Templates\TemplateEngineInterface;
	use Quellabs\DependencyInjection\Provider\ServiceProvider;
	use Quellabs\Discover\Discover;
	use Quellabs\Discover\Scanner\ComposerScanner;
	
	/**
	 * Service provider for template engines that uses discovery to find and instantiate
	 * the appropriate template engine implementation based on configuration.
	 */
	class TemplateEngineServiceProvider extends ServiceProvider {
		
		/**
		 * Cached singleton instance of the template engine.
		 * @var TemplateEngineInterface|null
		 */
		private static ?TemplateEngineInterface $instance = null;
		
		/**
		 * Determines if this service provider can handle the given class name.
		 * @param string $className The fully qualified class name to check
		 * @param array $metadata Metadata for filtering
		 * @return bool True if this provider supports the TemplateEngineInterface
		 */
		public function supports(string $className, array $metadata): bool {
			return $className === TemplateEngineInterface::class;
		}
		
		/**
		 * Creates and configures a template engine instance using discovery.
		 * @param string $className The class name to instantiate (should be TemplateEngineInterface)
		 * @param array $dependencies Array of resolved dependencies
		 * @return object The configured template engine instance
		 * @throws \RuntimeException If the preferred template engine is not found
		 */
		public function createInstance(string $className, array $dependencies): object {
			// Return existing instance if already created (singleton behavior)
			if (self::$instance !== null) {
				return self::$instance;
			}
			
			// Read template engine config - in real implementation this would come from config file/service
			$preferredEngine = 'smarty';
			
			// Initialize the discovery system to find available template engines
			$discover = new Discover();
			$discover->addScanner(new ComposerScanner('template-engine'));
			$discover->discover();
			
			// Find template engine providers that match our preferred engine type
			$providers = $discover->findProvidersByMetadata(function ($metadata) use ($preferredEngine) {
				// Check if the provider's metadata indicates it implements the preferred engine
				return isset($metadata['provider']) && $metadata['provider'] === $preferredEngine;
			});
			
			// Ensure we found at least one matching provider
			if (empty($providers)) {
				throw new \RuntimeException("Template engine '{$preferredEngine}' not found");
			}
			
			// Get the first matching template engine instance
			$templateEngine = $providers[0];

			// Ensure the discovered instance implements the expected interface
			if (!$templateEngine instanceof TemplateEngineInterface) {
				throw new \RuntimeException("Discovered template engine does not implement TemplateEngineInterface");
			}
			
			// Cache the instance for future requests (singleton pattern)
			self::$instance = $templateEngine;
			
			// Return the singleton instance
			return self::$instance;
		}
		
		/**
		 * Reset the singleton instance (useful for testing or configuration changes).
		 * @return void
		 */
		public static function resetInstance(): void {
			self::$instance = null;
		}
	}