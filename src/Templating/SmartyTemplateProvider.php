<?php
	
	namespace Quellabs\Canvas\Templating;
	
	use Smarty;
	use Quellabs\Discover\Provider\AbstractProvider;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	
	class SmartyTemplateProvider extends AbstractProvider implements TemplateEngineInterface, ProviderInterface {
		
		/**
		 * @var Smarty|null Smarty instance
		 */
		private ?Smarty $smarty = null;
		
		/**
		 * Returns the provider's metadata, which can be used to discover this class
		 * @return array
		 */
		public static function getMetadata(): array {
			return [
				'type'         => 'template_engine',
				'engine'       => 'smarty',
				'capabilities' => ['caching', 'inheritance', 'plugins'],
				'extensions'   => ['.tpl', '.smarty'],
				'version'      => '1.0.0'
			];
		}
		
		/**
		 * Returns the default settings for this provider
		 * @return array
		 */
		public static function getDefaults(): array {
			return [
				'template_dir' => dirname(__FILE__) . '/../Templates/',
				'compile_dir'  => dirname(__FILE__) . '/../Cache/Compile/',
				'cache_dir'    => dirname(__FILE__) . '/../Cache/Cache/',
				'debugging'    => false,
				'caching'      => true
			];
		}
		
		/**
		 * Renders a template using the Smarty template engine
		 * @param string $template The template file name/path to render
		 * @param array $data Associative array of variables to pass to the template
		 * @return string The rendered template content as a string
		 * @throws \RuntimeException If template rendering fails for any reason
		 */
		public function render(string $template, array $data = []): string {
			return $this->renderTemplate($template, $data, false);
		}
		
		/**
		 * Renders a template string with the provided data
		 * @param string $templateString The template content as a string
		 * @param array $data Associative array of variables to pass to the template
		 * @return string The rendered template content
		 * @throws \RuntimeException If template rendering fails for any reason
		 */
		public function renderString(string $templateString, array $data = []): string {
			return $this->renderTemplate($templateString, $data, true);
		}
		
		/**
		 * Adds a global variable that will be available in all templates
		 * @param string $key The variable name to use in templates
		 * @param mixed $value The value to assign (can be any type: string, array, object, etc.)
		 * @return void
		 */
		public function addGlobal(string $key, mixed $value): void {
			// Get the configured Smarty template engine instance
			$smarty = $this->getEngineInstance();
			
			// Assign the variable globally to the Smarty instance
			// This makes the variable available in all subsequent template renders
			// until the engine instance is reset or the variable is overwritten
			$smarty->assign($key, $value);
		}
		
		/**
		 * Checks if a template file exists and is accessible
		 * @param string $template The template file name/path to check for existence
		 * @return bool True if the template exists and is accessible, false otherwise
		 */
		public function exists(string $template): bool {
			try {
				// Get the configured Smarty template engine instance
				$smarty = $this->getEngineInstance();
				
				// Use Smarty's built-in method to check if the template file exists
				// This method considers the configured template directory and search paths
				return $smarty->templateExists($template);
			} catch (\Exception $e) {
				// If any exception occurs during the check (e.g., permission issues,
				// invalid paths, or Smarty configuration problems), treat it as "not found"
				// This provides a fail-safe behavior rather than propagating exceptions
				return false;
			}
		}
		
		/**
		 * Clears the Smarty template cache and optionally compiled templates
		 * @return void
		 * @throws \RuntimeException If cache clearing fails for any reason
		 */
		public function clearCache(): void {
			try {
				// Get the configured Smarty template engine instance
				$smarty = $this->getEngineInstance();
				
				// Clear all cache
				// This removes all cached template output, forcing templates to be
				// re-rendered and re-cached on the next request
				$smarty->clearAllCache();
				
				// Fetch the configuration
				$config = $this->getConfig();
				
				// Also clear compiled templates if needed
				// Check configuration to see if compiled templates should also be cleared
				if (isset($config['clear_compiled']) && $config['clear_compiled']) {
					// Clear compiled PHP templates that Smarty generates from template files
					// This is useful when template syntax or structure has changed
					$smarty->clearCompiledTemplate();
				}
			} catch (\Exception $e) {
				// If cache clearing fails, wrap the exception with more context
				// This could happen due to file permission issues or disk space problems
				throw new \RuntimeException("Failed to clear cache: " . $e->getMessage(), 0, $e);
			}
		}
		
		/**
		 * Additional helper methods for Smarty-specific functionality
		 */
		
		/**
		 * Register a custom function with Smarty
		 * @param string $name The function name to use in templates
		 * @param callable $callback Function that handles the custom functionality
		 * @return void
		 */
		public function registerFunction(string $name, callable $callback): void {
			try {
				// Get the configured Smarty template engine instance
				$smarty = $this->getEngineInstance();
				
				// Register the callback as a 'function' type plugin in Smarty
				// This allows the function to be called directly in templates
				$smarty->registerPlugin('function', $name, $callback);
			} catch (\SmartyException $e) {
				error_log("SmartyTemplateProvider: unable to register function {$name} ({$e->getMessage()}");
			}
		}
		
		/**
		 * Register a custom modifier with Smarty
		 * @param string $name The modifier name to use in templates
		 * @param callable $callback Function that transforms the input value
		 * @return void
		 */
		public function registerModifier(string $name, callable $callback): void {
			try {
				// Get the configured Smarty template engine instance
				$smarty = $this->getEngineInstance();
				
				// Register the callback as a 'modifier' type plugin in Smarty
				// This allows the modifier to be used with the pipe operator on variables
				$smarty->registerPlugin('modifier', $name, $callback);
			} catch (\SmartyException $e) {
				error_log("SmartyTemplateProvider: unable to register modifier {$name} ({$e->getMessage()}");
			}
		}
		
		/**
		 * Register a custom block with Smarty
		 * @param string $name The block name to use in templates
		 * @param callable $callback Function that processes the block content
		 * @return void
		 */
		public function registerBlock(string $name, callable $callback): void {
			try {
				// Get the configured Smarty template engine instance
				$smarty = $this->getEngineInstance();
				
				// Register the callback as a 'block' type plugin in Smarty
				// This allows the function to wrap and process template content
				$smarty->registerPlugin('block', $name, $callback);
			} catch (\SmartyException $e) {
				error_log("SmartyTemplateProvider: unable to register block {$name} ({$e->getMessage()}");
			}
		}
		
		/**
		 * Unlike clearCache() which clears all cached templates, this method
		 * removes the cache only for the specified template file. Useful when
		 * you know exactly which template needs to be refreshed.
		 * @param string $template The specific template file to clear from cache
		 * @return void
		 */
		public function clearTemplateCache(string $template): void {
			// Get the configured Smarty template engine instance
			$smarty = $this->getEngineInstance();
			
			// Clear cache only for the specified template
			// This is more efficient than clearing all cache when only one template changed
			$smarty->clearCache($template);
		}
		
		/**
		 * Check if a specific template is currently cached
		 * @param string $template The template file to check for cache status
		 * @return bool True if the template is cached, false if it needs to be rendered
		 */
		public function isCached(string $template): bool {
			try {
				// Get the configured Smarty template engine instance
				$smarty = $this->getEngineInstance();
				
				// Check if Smarty has a cached version of this template available
				// Returns true if cached and valid, false if needs rendering
				return $smarty->isCached($template);
			} catch (\SmartyException | \Exception $e) {
				return false;
			}
		}
		
		/**
		 * Gets or creates the Smarty template engine instance with proper configuration
		 * @return Smarty The configured Smarty template engine instance
		 */
		private function getEngineInstance(): Smarty {
			// Lazy initialization - only create Smarty instance if it doesn't exist yet
			if ($this->smarty === null) {
				// Create a new Smarty instance
				$this->smarty = new Smarty();
				
				// Get the current configuration settings for this wrapper
				$config = $this->getConfig();
				
				// Configure Smarty directories
				// Set up the three core directories Smarty needs, using config values
				// or falling back to class defaults if not specified
				$this->smarty->setTemplateDir($config['template_dir'] ?? static::getDefaults()['template_dir']);
				$this->smarty->setCompileDir($config['compile_dir'] ?? static::getDefaults()['compile_dir']);
				$this->smarty->setCacheDir($config['cache_dir'] ?? static::getDefaults()['cache_dir']);
				
				// Configure debugging and caching
				// Set up basic operational modes with fallback to defaults
				$this->smarty->debugging = $config['debugging'] ?? static::getDefaults()['debugging'];
				$this->smarty->caching = $config['caching'] ?? static::getDefaults()['caching'];
				
				// Set cache lifetime if specified
				// Only configure cache lifetime if explicitly provided in config
				if (isset($config['cache_lifetime'])) {
					$this->smarty->cache_lifetime = $config['cache_lifetime'];
				}
				
				// Enable security if specified
				// Optionally enable Smarty's security policy to restrict template operations
				if (isset($config['security']) && $config['security']) {
					try {
						$this->smarty->enableSecurity();
					} catch (\SmartyException $e) {
						error_log("SmartyTemplateProvider: unable to set Smarty security ({$e->getMessage()}");
					}
				}
			}
			
			// Return the configured (or previously configured) Smarty instance
			return $this->smarty;
		}
		
		/**
		 * Internal method to handle both file and string template rendering
		 * @param string $template The template file name/path or template string content
		 * @param array $data Associative array of variables to pass to the template
		 * @param bool $isString Whether the template parameter is a string (true) or file path (false)
		 * @return string The rendered template content
		 * @throws \RuntimeException If template rendering fails for any reason
		 */
		private function renderTemplate(string $template, array $data, bool $isString): string {
			try {
				// Get the configured Smarty template engine instance
				$smarty = $this->getEngineInstance();
				
				// Create a data object with local scope that inherits from the Smarty instance
				// This allows access to global variables while keeping local variables isolated
				// Local variables will override global ones with the same name
				$localData = $smarty->createData($smarty);
				
				// Assign variables to the local data object scope
				// These variables will only be available for this specific render call
				// and will override any global variables with the same name
				foreach ($data as $key => $value) {
					$localData->assign($key, $value);
				}
				
				// Determine the template source based on type
				$templateSource = $isString ? 'string:' . $template : $template;
				
				// Fetch/render the template with local data scope
				return $smarty->fetch($templateSource, $localData);
			} catch (\Exception $e) {
				// Create the appropriate error message based on the template type
				if ($isString) {
					$snippet = strlen($template) > 50 ? substr($template, 0, 50) . '...' : $template;
					$errorContext = "template string '{$snippet}'";
				} else {
					$errorContext = "template '{$template}'";
				}
				
				throw new \RuntimeException("Failed to render {$errorContext}: " . $e->getMessage(), 0, $e);
			}
		}
	}