<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Utilities\PSR4;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	use ReflectionClass;
	
	/**
	 * Scans directories for classes that implement ProviderInterface
	 *
	 * This scanner recursively traverses directories to find PHP classes that:
	 * 1. Match an optional naming pattern (e.g., '/Provider$/' for classes ending with "Provider")
	 * 2. Implement the ProviderInterface
	 */
	class DirectoryScanner implements ScannerInterface {
		
		/**
		 * Directories to scan
		 * @var array<string>
		 */
		protected array $directories = [];
		
		/**
		 * Optional regular expression pattern to filter class names
		 *
		 * Examples:
		 * - '/Provider$/' - Only classes ending with "Provider"
		 * - '/^App\\\\Service\\\\/' - Only classes in the App\Service namespace
		 * - null - No filtering, all classes implementing ProviderInterface are included
		 *
		 * @var string|null
		 */
		protected ?string $pattern;
		
		/**
		 * Default family name for discovered providers
		 * @var string
		 */
		protected string $defaultFamily;
		
		/**
		 * Cache of already scanned classes
		 * @var array<string, bool>
		 */
		protected array $scannedClasses = [];
		
		/**
		 * @var PSR4 PSR-4 utilities
		 */
		private PSR4 $utilities;
		
		/**
		 * DirectoryScanner constructor
		 * @param array<string> $directories Directories to scan
		 * @param string|null $pattern Regex pattern for class names (e.g., '/Provider$/')
		 * @param string $defaultFamily Default family name for discovered providers
		 */
		public function __construct(array $directories = [], ?string $pattern = null, string $defaultFamily = 'default') {
			$this->directories = $directories;
			$this->pattern = $pattern;
			$this->defaultFamily = $defaultFamily;
			$this->utilities = new PSR4();
		}
		
		/**
		 * Add a directory to scan
		 * @param string $directory
		 * @return self
		 */
		public function addDirectory(string $directory): self {
			if (!in_array($directory, $this->directories) && is_dir($directory)) {
				$this->directories[] = $directory;
			}
			
			return $this;
		}
		
		/**
		 * Set the class name pattern
		 * @param string $pattern
		 * @return self
		 */
		public function setPattern(string $pattern): self {
			$this->pattern = $pattern;
			return $this;
		}
		
		/**
		 * Scan directories for classes that implement ProviderInterface
		 * @param DiscoveryConfig $config
		 * @return array Array of provider data with class and family information
		 */
		public function scan(DiscoveryConfig $config): array {
			$providerData = [];
			$dirs = $this->directories;
			
			// If no directories specified, use config default directories
			if (empty($dirs)) {
				$dirs = $config->getDefaultDirectories();
			}
			
			foreach ($dirs as $directory) {
				$providerData = array_merge(
					$providerData,
					$this->scanDirectory($directory, $config->isDebugEnabled())
				);
			}
			
			return $providerData;
		}
		
		/**
		 * Set a common naming convention pattern
		 * @param string $convention The naming convention to use ('suffix', 'prefix', or 'namespace')
		 * @param string $value The value to match (e.g., 'Provider' for suffix)
		 * @return self
		 */
		public function setNamingConvention(string $convention, string $value): self {
			switch (strtolower($convention)) {
				case 'suffix':
					$this->pattern = '/' . preg_quote($value) . '$/';
					break;
				
				case 'prefix':
					$this->pattern = '/^' . preg_quote($value) . '/';
					break;
				
				case 'namespace':
					$this->pattern = '/^' . str_replace('\\', '\\\\', $value) . '\\\\/';
					break;
				
				default:
					throw new \InvalidArgumentException("Unknown naming convention: {$convention}");
			}
			
			return $this;
		}
		
		/**
		 * This function traverses a directory structure, identifies all PHP files,
		 * attempts to extract class names from them, and checks if each class implements
		 * the ProviderInterface. All valid provider class data is returned.
		 * @param string $directory The root directory path to begin scanning
		 * @param bool $debug Whether to output debug messages during the scanning process
		 * @return array Array of provider data with class and family information
		 */
		protected function scanDirectory(string $directory, bool $debug = false): array {
			// Verify the directory exists and is accessible before attempting to scan
			if (!is_dir($directory) || !is_readable($directory)) {
				// Log warning if the directory can't be accessed and debug is enabled
				if ($debug) {
					echo "[WARNING] Directory not readable: {$directory}\n";
				}
				
				return [];
			}
			
			// Fetch all provider classes found in the directory
			$classes = $this->utilities->findClassesInDirectory($directory, function($className) use ($debug) {
				// Check if the class is a valid provider
				return $this->isValidProviderClass($className, $debug);
			});
			
			// Process each valid class found in the directory structure
			$providerData = [];
			
			foreach ($classes as $className) {
				// Add the provider data to our results
				$providerData[] = [
					'class'  => $className,
					'family' => $this->defaultFamily,
					'config' => null
				];
			}
			
			// Return all discovered provider class data from the directory
			return $providerData;
		}
		
		/**
		 * Check if a class implements ProviderInterface and matches the pattern
		 * @param string $className Fully qualified class name to check
		 * @param bool $debug Whether to output error messages when exceptions occur
		 * @return bool True if the class is a valid provider, false otherwise
		 */
		protected function isValidProviderClass(string $className, bool $debug = false): bool {
			// Skip already scanned classes to prevent duplicate processing
			// This improves performance when scanning large codebases
			if (isset($this->scannedClasses[$className])) {
				return false;
			}
			
			// Mark this class as scanned for future reference
			$this->scannedClasses[$className] = true;
			
			try {
				// Attempt to load the class using PHP's autoloader
				// Returns false if the class doesn't exist or can't be loaded
				if (!class_exists($className)) {
					return false;
				}
				
				// If a naming pattern was specified, check if the class name matches
				// This allows filtering for specific naming conventions (e.g., all classes ending with "Provider")
				if ($this->pattern !== null && !preg_match($this->pattern, $className)) {
					return false;
				}
				
				// Create a reflection instance to inspect the class's properties and interfaces
				$reflectionClass = new ReflectionClass($className);
				
				// Abstract classes cannot be instantiated, so they can't be used as providers
				// This prevents attempting to instantiate abstract classes later
				if ($reflectionClass->isAbstract()) {
					return false;
				}
				
				// Final check: verify that the class implements the required interface
				// Only classes implementing ProviderInterface are considered valid providers
				return $reflectionClass->implementsInterface(ProviderInterface::class);
				
			} catch (\Throwable $e) {
				// Handle any exceptions that might occur during class inspection
				// Common issues include autoloading errors or reflection failures
				if ($debug) {
					echo "[ERROR] Failed to check class {$className}: {$e->getMessage()}\n";
				}
				
				return false;
			}
		}
	}