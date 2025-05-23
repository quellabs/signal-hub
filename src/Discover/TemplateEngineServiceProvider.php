<?php
	
	namespace Quellabs\Canvas\Discover;
	
	use Quellabs\Canvas\Templating\TemplateEngineInterface;
	use Quellabs\DependencyInjection\Provider\ServiceProvider;
	use Quellabs\Discover\Discover;
	use Quellabs\Discover\Scanner\ComposerScanner;
	
	/**
	 * Service provider for template engines that uses discovery to find and instantiate
	 * the appropriate template engine implementation based on configuration.
	 */
	class TemplateEngineServiceProvider extends ServiceProvider {
		
		/**
		 * Determines if this service provider can handle the given class name.
		 * @param string $className The fully qualified class name to check
		 * @return bool True if this provider supports the TemplateEngineInterface
		 */
		public function supports(string $className): bool {
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
			// Read template engine config - in real implementation this would come from config file/service
			$preferredEngine = 'smarty';
			
			// Initialize the discovery system to find available template engines
			$discover = new Discover();
			$discover->addScanner(new ComposerScanner('template_engine'));
			$discover->discover();
			
			// Find template engine providers that match our preferred engine type
			$providers = $discover->findProvidersByMetadata(function ($metadata) use ($preferredEngine) {
				// Check if the provider's metadata indicates it implements the preferred engine
				return isset($metadata['engine']) && $metadata['engine'] === $preferredEngine;
			});
			
			// Ensure we found at least one matching provider
			if (empty($providers)) {
				throw new \RuntimeException("Template engine '{$preferredEngine}' not found");
			}
			
			// Return the first matching provider (could be enhanced to support priority/ranking)
			// Note: In a complete implementation, this would likely instantiate the provider
			// rather than returning the provider class itself
			return $providers[0];
		}
	}