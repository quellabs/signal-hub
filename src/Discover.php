<?php
	
	namespace Quellabs\Discover;
	
	use Composer\Autoload\ClassLoader;
	use Quellabs\Discover\Scanner\ScannerInterface;
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\Discover\Config\DiscoveryConfig;
	
	class Discover {
		
		/**
		 * @var array<ScannerInterface>
		 */
		protected array $scanners = [];
		
		/**
		 * @var array<ProviderInterface>
		 */
		protected array $providers = [];
		
		/**
		 * @var DiscoveryConfig
		 */
		protected DiscoveryConfig $config;
		
		/**
		 * @var string|null Cached local json path
		 */
		protected ?string $composerJsonPathCache;
		
		/**
		 * Create a new Discover instance
		 * @param DiscoveryConfig|null $config
		 */
		public function __construct(?DiscoveryConfig $config = null) {
			$this->config = $config ?? new DiscoveryConfig();
			$this->composerJsonPathCache = null;
		}
		
		/**
		 * Discover providers using all registered scanners
		 * @return self
		 */
		public function discover(): self {
			foreach ($this->scanners as $scanner) {
				$discoveredProviders = $scanner->scan($this->config);
				
				foreach ($discoveredProviders as $provider) {
					if ($provider instanceof ProviderInterface) {
						$this->addProvider($provider);
					}
				}
			}
			
			return $this;
		}
		
		/**
		 * Get all discovered providers
		 * @return array<ProviderInterface>
		 */
		public function getProviders(): array {
			return $this->providers;
		}
		
		/**
		 * Clear all discovered providers
		 * @return self
		 */
		public function clearProviders(): self {
			$this->providers = [];
			return $this;
		}
		
		/**
		 * Get the current configuration
		 * @return DiscoveryConfig
		 */
		public function getConfig(): DiscoveryConfig {
			return $this->config;
		}
		
		/**
		 * Set a new configuration
		 * @param DiscoveryConfig $config
		 * @return self
		 */
		public function setConfig(DiscoveryConfig $config): self {
			$this->config = $config;
			return $this;
		}
		
		/**
		 * Add a scanner
		 * @param ScannerInterface $scanner
		 * @return self
		 */
		public function addScanner(ScannerInterface $scanner): self {
			$this->scanners[] = $scanner;
			return $this;
		}
		
		/**
		 * This method adds a service provider to the internal providers collection,
		 * but only if a provider of the same class doesn't already exist and
		 * the provider indicates it should be loaded.
		 * @param ProviderInterface $provider The service provider instance to add
		 * @return self Returns $this for method chaining
		 */
		public function addProvider(ProviderInterface $provider): self {
			// Get the fully qualified class name of the provider
			$className = get_class($provider);
			
			// Flag to track if this provider class already exists in our collection
			$exists = false;
			
			// Check if a provider of the same class is already registered
			foreach ($this->providers as $existingProvider) {
				if (get_class($existingProvider) === $className) {
					$exists = true;
					break;
				}
			}
			
			// Only add the provider if:
			// 1. It doesn't already exist in our collection
			// 2. The provider itself indicates it should be loaded (via shouldLoad())
			if (!$exists && $provider->shouldLoad()) {
				$this->providers[] = $provider;
			}
			
			// Return $this to allow method chaining
			return $this;
		}
		
		/**
		 * Get providers that provide a specific service
		 * @param string $service
		 * @return array<ProviderInterface>
		 */
		public function getProvidersForService(string $service): array {
			return array_filter($this->providers, function (ProviderInterface $provider) use ($service) {
				return in_array($service, $provider->provides());
			});
		}
		
		/**
		 * Find the path to the local composer.json file
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 * @return string|null Path to composer.json if found, null otherwise
		 */
		public function findComposerJsonPath(?string $startDirectory = null): ?string {
			// Get the result from cache if we can
			if ($this->composerJsonPathCache !== null) {
				return $this->composerJsonPathCache;
			}
			
			// Start from provided directory or current directory if not specified
			$directory = $startDirectory ?? getcwd();
			
			// Ensure we have a valid directory
			if (!$directory || !is_dir($directory)) {
				return null;
			}
			
			// Convert to absolute path if it's not already
			$directory = realpath($directory);
			
			// Keep traversing up until we find composer.json or reach the filesystem root
			while ($directory) {
				$composerPath = $directory . DIRECTORY_SEPARATOR . 'composer.json';
				
				if (file_exists($composerPath)) {
					return $this->composerJsonPathCache = $composerPath;
				}
				
				// Get parent directory
				$parentDir = dirname($directory);
				
				// Stop if we've reached the filesystem root
				if ($parentDir === $directory) {
					break;
				}
				
				$directory = $parentDir;
			}
			
			return null;
		}
		
		/**
		 * Gets the Composer autoloader instance
		 * @return ClassLoader
		 * @throws \RuntimeException If autoloader can't be found
		 */
		public function getComposerAutoloader(): ClassLoader {
			// Try to find the Composer autoloader
			foreach (spl_autoload_functions() as $autoloader) {
				if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
					return $autoloader[0];
				}
			}
			
			// Look for the autoloader in common locations
			$autoloaderPaths = [
				// From the current working directory
				getcwd() . '/vendor/autoload.php',
				
				// From this file's directory, going up to find vendor
				dirname(__DIR__, 3) . '/vendor/autoload.php',
				dirname(__DIR__, 4) . '/vendor/autoload.php',
			];
			
			foreach ($autoloaderPaths as $path) {
				if (file_exists($path)) {
					return require $path;
				}
			}
			
			throw new \RuntimeException('Could not find Composer autoloader');
		}
		
		/**
		 * Recursively scans a directory and maps files to namespaced classes based on PSR-4 rules
		 * @param string $directory Directory to scan
		 * @param array<string, string[]> $psr4Prefixes PSR-4 namespace prefixes and their dirs
		 * @param string $controllerSuffix Suffix to filter controller classes (optional)
		 * @return array<string> Array of fully qualified class names
		 */
		public function scanDirectoryWithPsr4(string $directory, array $psr4Prefixes, string $controllerSuffix = ''): array {
			// Early return if directory doesn't exist or is not readable
			$absoluteDir = realpath($directory);
			
			if (!$absoluteDir) {
				return [];
			}
			
			// Find matching PSR-4 namespace for this directory
			$namespaceForDir = $this->findMatchingNamespace($absoluteDir, $psr4Prefixes);
			
			// Get directory entries or return an empty array if scandir fails
			$classNames = [];
			$entries = scandir($absoluteDir) ?: [];
			
			foreach ($entries as $entry) {
				// Skip current directory, parent directory, and hidden files
				if ($this->shouldSkipEntry($entry)) {
					continue;
				}
				
				// Fetch the full path
				$fullPath = $absoluteDir . DIRECTORY_SEPARATOR . $entry;
				
				// Recursively scan subdirectories and merge results
				if (is_dir($fullPath)) {
					$subDirClasses = $this->scanDirectoryWithPsr4($fullPath, $psr4Prefixes, $controllerSuffix);
					$classNames = array_merge($classNames, $subDirClasses);
					continue; // Early continue to next iteration
				}
				
				// Skip if not a PHP file or no namespace was found for the directory
				if (!$this->isPhpFile($entry) || !$namespaceForDir) {
					continue;
				}
				
				// Fetch class name from the file
				$className = $this->getClassNameFromFile($entry);

				// Skip if it doesn't match the controller suffix (when specified)
				if (!$this->matchesControllerSuffix($className, $controllerSuffix)) {
					continue;
				}
				
				$fullyQualifiedName = $namespaceForDir . '\\' . $className;
				
				// Only add class if it exists and is loadable
				if (class_exists($fullyQualifiedName)) {
					$classNames[] = $fullyQualifiedName;
				}
			}
			
			return $classNames;
		}
		
		/**
		 * Maps a directory path to a namespace based on PSR-4 rules
		 * @param string $directory Directory path to map
		 * @param string $psr4RootDir PSR-4 root directory
		 * @param string $namespacePrefix PSR-4 namespace prefix
		 * @return string The corresponding namespace
		 */
		public function mapDirectoryToNamespace(string $directory, string $psr4RootDir, string $namespacePrefix): string {
			// Get the relative path from the PSR-4 root
			$relativePath = '';
			
			// Only extract the relative path if the directory is longer than the root directory
			// This prevents negative offsets in substr() when directory is shorter than root
			if (strlen($directory) > strlen($psr4RootDir)) {
				$relativePath = substr($directory, strlen($psr4RootDir) + 1);
				// The +1 skips the directory separator after the root path
			}
			
			// Convert directory separators to namespace separators
			// This transforms filesystem paths (with / or \) into PHP namespace format (with \)
			$namespaceSuffix = str_replace(
				DIRECTORY_SEPARATOR,  // Platform-specific directory separator (/ on Unix, \ on Windows)
				'\\',                 // PHP namespace separator
				$relativePath
			);
			
			// Combine the prefix with the suffix
			// rtrim ensures we don't have double backslashes between prefix and suffix
			return
				rtrim($namespacePrefix, '\\') .
				(empty($namespaceSuffix) ? '' : '\\' . $namespaceSuffix);
		}
		
		/**
		 * Finds the matching PSR-4 namespace for a given directory
		 * @param string $directory Absolute directory path
		 * @param array<string, string[]> $psr4Prefixes PSR-4 namespace prefixes mapping to possible directories
		 * @return string|null Matched namespace or null if not found
		 */
		public function findMatchingNamespace(string $directory, array $psr4Prefixes): ?string {
			// Will hold the namespace that best matches the directory
			$matchedNamespace = null;
			
			// Tracks the length of the longest matching PSR-4 directory path
			$longestMatch = 0;
			
			// Iterate through each namespace prefix and its corresponding directories
			foreach ($psr4Prefixes as $prefix => $dirs) {
				// A prefix might map to multiple directories, check each one
				foreach ($dirs as $psr4Dir) {
					// Convert relative paths to absolute paths
					$psr4AbsoluteDir = realpath($psr4Dir);
					
					// Skip invalid directories that can't be resolved
					if (!$psr4AbsoluteDir) {
						continue;
					}
					
					// Check if the target directory is within this PSR-4 root directory
					if (!str_starts_with($directory, $psr4AbsoluteDir)) {
						continue;
					}
					
					// Calculate how much of the path matches to find the most specific match
					$matchLength = strlen($psr4AbsoluteDir);
					
					// If this match is longer than previous matches, it's more specific
					if ($matchLength > $longestMatch) {
						$longestMatch = $matchLength;
						
						// Transform the directory path into a namespace using the prefix
						$matchedNamespace = $this->mapDirectoryToNamespace(
							$directory,
							$psr4AbsoluteDir,
							$prefix
						);
					}
				}
			}
			
			// Return the namespace corresponding to the most specific (longest) matching directory
			// or null if no match was found
			return $matchedNamespace;
		}
		
		/**
		 * Checks if an entry should be skipped during directory scanning
		 * @param string $entry Directory entry name
		 * @return bool True if entry should be skipped
		 */
		private function shouldSkipEntry(string $entry): bool {
			return in_array($entry, ['.', '..', '.htaccess'], true);
		}
		
		/**
		 * Checks if a file is a PHP file
		 * @param string $filename Filename to check
		 * @return bool True if file is a PHP file
		 */
		private function isPhpFile(string $filename): bool {
			return str_ends_with($filename, '.php');
		}
		
		/**
		 * Gets class name from a file path
		 * @param string $filename File name
		 * @return string Class name
		 */
		private function getClassNameFromFile(string $filename): string {
			return pathinfo($filename, PATHINFO_FILENAME);
		}
		
		/**
		 * Checks if a class name matches the controller suffix requirement
		 * @param string $className Class name to check
		 * @param string $controllerSuffix Required suffix (if any)
		 * @return bool True if matches or no suffix required
		 */
		private function matchesControllerSuffix(string $className, string $controllerSuffix): bool {
			return empty($controllerSuffix) || str_ends_with($className, $controllerSuffix);
		}
	}