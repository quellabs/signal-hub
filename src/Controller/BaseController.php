<?php
	
	namespace Quellabs\Canvas\Controller;
	
	use Quellabs\Discover\Discover;
	use Quellabs\Discover\Scanner\ComposerScanner;
	
	/**
	 * Base controller providing common functionality for all controllers
	 */
	class BaseController {
		
		/**
		 * Shared Discovery instance across all controller instances
		 * @var Discover|null
		 */
		private static ?Discover $discovery = null;
		
		/**
		 * Get the shared Discovery instance, initializing it if necessary
		 * @return Discover The configured Discovery instance
		 */
		protected function getDiscovery(): Discover {
			// Check if Discovery has been initialized yet
			if (self::$discovery === null) {
				self::$discovery = new Discover();
				self::$discovery->addScanner(new ComposerScanner('template_engine'));
				self::$discovery->discover();
			}
			
			// Return the shared, configured Discovery instance
			return self::$discovery;
		}
		
		/**
		 * Render a template using the discovered template engine
		 * @param string $template The template file path to render
		 * @param array $data Associative array of data to pass to the template
		 * @return string The rendered template content
		 * @throws \RuntimeException If no suitable template engine provider is found
		 */
		protected function render(string $template, array $data = []): string {
			// Search for template providers that support the 'smarty' engine
			// The metadata check looks for providers with 'engine' => 'smarty' in their metadata
			$providers = $this->getDiscovery()->findProvidersByMetadata(function($metadata) {
				return isset($metadata['engine']) && ($metadata['engine'] === 'smarty');
			});
			
			// If a suitable provider is found, use it to render the template
			if (!empty($providers)) {
				// Get the first matching provider and delegate rendering to it
				// The provider was instantiated by Discovery with proper configuration
				return $providers[0]->render($template, $data);
			}
			
			// Throw exception if no Smarty template engine provider was discovered
			throw new \RuntimeException("Template engine not found");
		}
	}