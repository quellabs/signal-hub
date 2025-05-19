<?php
	
	namespace Quellabs\Discover\Config;
	
	/**
	 * Configuration options for the discovery process
	 */
	class DiscoveryConfig {

		/**
		 * Whether debug mode is enabled
		 * @var bool
		 */
		protected bool $debug = false;
		
		/**
		 * Default directories to scan if none specified
		 * @var array<string>
		 */
		protected array $defaultDirectories = [];
		
		/**
		 * Configuration key to look for in composer.json
		 * @var string
		 */
		protected string $composerConfigKey = 'discover';
		
		/**
		 * Cache provider results
		 * @var bool
		 */
		protected bool $cacheEnabled = false;
		
		/**
		 * Path to cache file
		 * @var string|null
		 */
		protected ?string $cachePath = null;
		
		/**
		 * Auto-load providers after discovery
		 * @var bool
		 */
		protected bool $autoload = true;
		
		/**
		 * Create a new DiscoveryConfig instance
		 * @param array<string, mixed> $config Configuration options
		 */
		public function __construct(array $config = []) {
			// Set default config values
			$this->applyDefaults();
			
			// Apply provided config options
			foreach ($config as $key => $value) {
				$method = 'set' . ucfirst($key);
				
				if (method_exists($this, $method)) {
					$this->$method($value);
				}
			}
		}
		
		/**
		 * Apply default configuration
		 * @return void
		 */
		protected function applyDefaults(): void {
			// Set default directories to scan
			$this->defaultDirectories = [
				getcwd() . '/app/Providers',
				getcwd() . '/src/Providers'
			];
			
			// Set default cache path
			$this->cachePath = getcwd() . '/bootstrap/cache/providers.php';
		}
		
		/**
		 * Create a new config instance from array
		 * @param array<string, mixed> $config
		 * @return static
		 */
		public static function fromArray(array $config): self {
			return new static($config);
		}
		
		/**
		 * Enable or disable debug mode
		 * @param bool $debug
		 * @return self
		 */
		public function setDebug(bool $debug): self {
			$this->debug = $debug;
			return $this;
		}
		
		/**
		 * Check if debug mode is enabled
		 * @return bool
		 */
		public function isDebugEnabled(): bool {
			return $this->debug;
		}
		
		/**
		 * Set default directories to scan
		 * @param array<string> $directories
		 * @return self
		 */
		public function setDefaultDirectories(array $directories): self {
			$this->defaultDirectories = $directories;
			return $this;
		}
		
		/**
		 * Add a default directory to scan
		 * @param string $directory
		 * @return self
		 */
		public function addDefaultDirectory(string $directory): self {
			if (!in_array($directory, $this->defaultDirectories)) {
				$this->defaultDirectories[] = $directory;
			}
			
			return $this;
		}
		
		/**
		 * Get default directories to scan
		 * @return array<string>
		 */
		public function getDefaultDirectories(): array {
			return $this->defaultDirectories;
		}
		
		/**
		 * Set the composer config key
		 * @param string $key
		 * @return self
		 */
		public function setComposerConfigKey(string $key): self {
			$this->composerConfigKey = $key;
			return $this;
		}
		
		/**
		 * Get the composer config key
		 * @return string
		 */
		public function getComposerConfigKey(): string {
			return $this->composerConfigKey;
		}
		
		/**
		 * Enable or disable caching
		 * @param bool $enabled
		 * @return self
		 */
		public function setCacheEnabled(bool $enabled): self {
			$this->cacheEnabled = $enabled;
			return $this;
		}
		
		/**
		 * Check if caching is enabled
		 * @return bool
		 */
		public function isCacheEnabled(): bool {
			return $this->cacheEnabled;
		}
		
		/**
		 * Set the cache file path
		 * @param string $path
		 * @return self
		 */
		public function setCachePath(string $path): self {
			$this->cachePath = $path;
			return $this;
		}
		
		/**
		 * Get the cache file path
		 * @return string|null
		 */
		public function getCachePath(): ?string {
			return $this->cachePath;
		}
		
		/**
		 * Enable or disable auto-loading of providers
		 * @param bool $autoload
		 * @return self
		 */
		public function setAutoload(bool $autoload): self {
			$this->autoload = $autoload;
			return $this;
		}
		
		/**
		 * Check if autoloading is enabled
		 * @return bool
		 */
		public function isAutoloadEnabled(): bool {
			return $this->autoload;
		}
		
		/**
		 * Convert config to array
		 * @return array<string, mixed>
		 */
		public function toArray(): array {
			return [
				'debug'              => $this->debug,
				'defaultDirectories' => $this->defaultDirectories,
				'composerConfigKey'  => $this->composerConfigKey,
				'cacheEnabled'       => $this->cacheEnabled,
				'cachePath'          => $this->cachePath,
				'autoload'           => $this->autoload,
			];
		}
	}