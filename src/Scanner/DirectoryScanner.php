<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Composer\Autoload\ClassLoader;
	use FilesystemIterator;
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Provider\ProviderInterface;
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
		 * Cache of already scanned classes
		 * @var array<string, bool>
		 */
		protected array $scannedClasses = [];
		
		/**
		 * Cache for the Composer autoloader instance
		 * @var ClassLoader|null
		 */
		private ?ClassLoader $autoloaderCache = null;
		
		/**
		 * DirectoryScanner constructor
		 * @param array<string> $directories Directories to scan
		 * @param string|null $pattern Regex pattern for class names (e.g., '/Provider$/')
		 */
		public function __construct(array $directories = [], ?string $pattern = null) {
			$this->directories = $directories;
			$this->pattern = $pattern;
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
		 * @return array<ProviderInterface>
		 */
		public function scan(DiscoveryConfig $config): array {
			$providers = [];
			$dirs = $this->directories;
			
			// If no directories specified, use config default directories
			if (empty($dirs)) {
				$dirs = $config->getDefaultDirectories();
			}
			
			foreach ($dirs as $directory) {
				$providers = array_merge(
					$providers,
					$this->scanDirectory($directory, $config->isDebugEnabled())
				);
			}
			
			return $providers;
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
		 * the ProviderInterface. All valid providers are instantiated and returned.
		 * @param string $directory The root directory path to begin scanning
		 * @param bool $debug Whether to output debug messages during the scanning process
		 * @return array<ProviderInterface> Array of successfully instantiated provider objects found in the directory
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
			
			// Initialize an empty array to store discovered provider instances
			$providers = [];
			
			// Fetch all php files in the directory
			$phpFiles = $this->getPhpFiles($directory);
			
			// Process each file found in the directory structure
			foreach ($phpFiles as $file) {
				// Attempt to extract the fully qualified class name from the file
				$className = $this->getClassNameFromFile($file);
				
				// If a class name was successfully extracted
				if (!$className) {
					continue;
				}
				
				// Check if the class is a valid provider
				if (!$this->isProvider($className, $debug)) {
					continue;
				}
				
				// Instantiate the provider
				$provider = $this->instantiateProvider($className, $debug);
				
				// Add the provider to the results if it's valid and not a duplicate
				// Using strict comparison (===) for the in_array check to ensure object identity
				if (!in_array($provider, $providers, true)) {
					$providers[] = $provider;
				}
			}
			
			// Return all successfully instantiated provider objects discovered in the directory
			return $providers;
		}
		
		/**
		 * Get class name from a file path using Composer's autoloader
		 * @param string $filePath
		 * @return string|null
		 */
		protected function getClassNameFromFile(string $filePath): ?string {
			// Get the absolute, normalized path
			$realPath = realpath($filePath);
			
			// Couldn't resolve the path
			if ($realPath === false) {
				return null;
			}
			
			// Get Composer's autoloader
			$autoloader = $this->findAutoloader();
			
			// Couldn't find the autoloader
			if ($autoloader === null) {
				return null;
			}
			
			// Get the class map from the autoloader
			$classMap = $autoloader->getClassMap();
			
			// Find the class that maps to this file
			$className = array_search($realPath, $classMap);
			
			if ($className !== false) {
				return $className; // Found in the class map
			}
			
			// If not in the class map, try to infer from PSR-4 autoloading rules
			foreach ($autoloader->getPrefixesPsr4() as $namespace => $directories) {
				foreach ($directories as $directory) {
					$directory = realpath($directory);
					
					if ($directory && str_starts_with($realPath, $directory)) {
						// File is within this PSR-4 directory
						$relPath = substr($realPath, strlen($directory) + 1);
						$relPath = str_replace('.php', '', $relPath);
						$relPath = str_replace('/', '\\', $relPath);
						
						return rtrim($namespace, '\\') . '\\' . $relPath;
					}
				}
			}
			
			return null;
		}
		
		/**
		 * Find the Composer autoloader, using a cached instance if available
		 *
		 * This method attempts to locate the Composer ClassLoader instance by:
		 * 1. Checking if it's already cached in the instance property
		 * 2. Examining registered autoload functions
		 * 3. Trying to load it from common file locations
		 *
		 * @return ClassLoader|null The Composer autoloader instance, or null if not found
		 */
		protected function findAutoloader(): ?ClassLoader {
			// Return cached autoloader if it exists
			if ($this->autoloaderCache !== null) {
				return $this->autoloaderCache;
			}
			
			// First check if we can get it from the loader that loaded this class
			foreach (spl_autoload_functions() as $function) {
				if (is_array($function) && $function[0] instanceof ClassLoader) {
					// Cache the found autoloader for future use
					$this->autoloaderCache = $function[0];
					return $this->autoloaderCache;
				}
			}
			
			// Try common locations relative to the current file
			$locations = [
				// When used as a dependency in another project
				dirname(__DIR__, 4) . '/vendor/autoload.php',
				
				// When used directly
				dirname(__DIR__) . '/vendor/autoload.php',
				
				// Other common locations
				dirname(__DIR__, 3) . '/autoload.php',
				dirname(__DIR__, 2) . '/autoload.php',
			];
			
			foreach ($locations as $location) {
				if (file_exists($location)) {
					// Cache the autoloader before returning it
					$this->autoloaderCache = require $location;
					return $this->autoloaderCache;
				}
			}
			
			return null;
		}
		
		/**
		 * Check if a class implements ProviderInterface and matches the pattern
		 *
		 * This method performs a series of validations to determine if a class qualifies
		 * as a valid provider according to the scanner's criteria. A class is considered
		 * a valid provider if it:
		 *
		 * 1. Has not been previously scanned
		 * 2. Exists and can be autoloaded
		 * 3. Matches the naming pattern (if a pattern is set)
		 * 4. Is not abstract
		 * 5. Implements the ProviderInterface
		 *
		 * @param string $className Fully qualified class name to check
		 * @param bool $debug Whether to output error messages when exceptions occur
		 * @return bool True if the class is a valid provider, false otherwise
		 */
		protected function isProvider(string $className, bool $debug = false): bool {
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
		
		/**
		 * Create an instance of a provider class
		 * @param string $className
		 * @param bool $debug
		 * @return ProviderInterface|null
		 */
		protected function instantiateProvider(string $className, bool $debug = false): ?ProviderInterface {
			try {
				$reflectionClass = new ReflectionClass($className);
				return $reflectionClass->newInstance();
			} catch (\Throwable $e) {
				if ($debug) {
					echo "[ERROR] Failed to instantiate provider {$className}: {$e->getMessage()}\n";
				}
				
				return null;
			}
		}
		
		/**
		 * Get all PHP files in a directory recursively
		 * @param string $directory The absolute path to the directory to scan
		 * @return array<string> Array of absolute file paths to all PHP files found
		 */
		protected function getPhpFiles(string $directory): array {
			// Initialize an empty array to store collected file paths
			$files = [];
			
			// Create a recursive directory iterator to traverse all subdirectories
			// RecursiveDirectoryIterator gets entries in a directory
			// RecursiveIteratorIterator allows iteration through all nested directories
			// SKIP_DOTS ensures "." and ".." directory entries are skipped
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST  // Process parent directories before their children
			);
			
			// Iterate through each file/directory entry found
			foreach ($iterator as $file) {
				// Only collect entries that are:
				// 1. Files (not directories)
				// 2. Have the .php extension
				if ($file->isFile() && $file->getExtension() === 'php') {
					// Add the full path of the PHP file to our collection
					$files[] = $file->getPathname();
				}
			}
			
			// Return the collected list of PHP file paths
			return $files;
		}
	}