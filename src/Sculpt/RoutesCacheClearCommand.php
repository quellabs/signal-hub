<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\Canvas\Kernel;
	use Quellabs\Canvas\Routing\AnnotationResolver;
	use Quellabs\Sculpt\ConfigurationManager;
	use Symfony\Component\HttpFoundation\Request;
	
	class RoutesCacheClearCommand extends RoutesBase {
		
		/**
		 * Returns the signature of this command
		 * @return string
		 */
		public function getSignature(): string {
			return "route:clear_cache";
		}
		
		/**
		 * Returns a brief description of what this command is for
		 * @return string
		 */
		public function getDescription(): string {
			return "Clear routing cache";
		}
		
		/**
		 * List the routes
		 * @param ConfigurationManager $config
		 * @return int
		 */
		public function execute(ConfigurationManager $config): int {
			// Fetch contents of app.php
			$providerConfig = $this->getProvider()->getConfig();
			
			// Determine the cache directory
			$cacheDirectory = $providerConfig['cache_dir'] ?? __DIR__ . "/../../storage/cache";
			
			// Remove the cache file
			@unlink($cacheDirectory . "/routes.json");
			
			// Show message
			$this->output->success("Routes cache cleared");
			
			// Return success status
			return 0;
		}
	}