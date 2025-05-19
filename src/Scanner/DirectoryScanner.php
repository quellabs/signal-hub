<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Composer\Autoload\ClassLoader;
	use FilesystemIterator;
	use Quellabs\Discover\Config\DiscoveryConfig;
	use Quellabs\Discover\Provider\ProviderInterface;
	use ReflectionClass;
	
	/**
	 * Scans directories for classes that implement ProviderInterface
	 */
	class DirectoryScanner implements ScannerInterface {
		
		/**
		 * Directories to scan
		 *
		 * @var array<string>
		 */
		protected array $directories = [];
		
		/**
		 * Class name pattern to match (regex)
		 *
		 * @var string|null
		 */
		protected ?string $pattern;
		
		/**
		 * Cache of already scanned classes
		 *
		 * @var array<string, bool>
		 */
		protected array $scannedClasses = [];
		
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
		 * This function traverses a directory structure, identifies all PHP files,
		 * attempts to extract class names from them, and checks if each class implements
		 * the ProviderInterface. All valid providers are instantiated and returned.
		 * @param string $directory The root directory path to begin scanning
		 * @param bool $debug Whether to output debug messages during the scanning process
		 * @return array<ProviderInterface> Array of successfully instantiated provider objects found in the directory
		 */
		protected function scanDirectory(string $directory, bool $debug = false): array {
			// Initialize an empty array to store discovered provider instances
			$providers = [];
			
			// Verify the directory exists and is accessible before attempting to scan
			if (!is_dir($directory) || !is_readable($directory)) {
				// Log warning if the directory can't be accessed and debug is enabled
				if ($debug) {
					echo "[WARNING] Directory not readable: {$directory}\n";
				}
				
				return $providers;
			}
			
			// Create a recursive directory iterator to traverse all subdirectories
			// SKIP_DOTS ensures "." and ".." directory entries are skipped
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST  // Process parent directories before their children
			);
			
			// Process each file found in the directory structure
			foreach ($iterator as $file) {
				// Only process PHP files, skip directories and non-PHP files
				if ($file->isFile() && $file->getExtension() === 'php') {
					// Attempt to extract the fully qualified class name from the file
					$className = $this->getClassNameFromFile($file->getPathname());
					
					// If a class name was successfully extracted
					if ($className) {
						// Check if the class implements ProviderInterface and instantiate it
						$provider = $this->checkClassIsProvider($className, $debug);
						
						// Add the provider to the results if it's valid and not a duplicate
						// Using strict comparison (===) for the in_array check to ensure object identity
						if ($provider && !in_array($provider, $providers, true)) {
							$providers[] = $provider;
						}
					}
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
		 * Find the Composer autoloader
		 * @return ClassLoader|null
		 */
		protected function findAutoloader(): ?ClassLoader {
			// First check if we can get it from the loader that loaded this class
			foreach (spl_autoload_functions() as $function) {
				if (is_array($function) && $function[0] instanceof ClassLoader) {
					return $function[0];
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
					return require $location;
				}
			}
			
			return null;
		}
		
		/**
		 * Check if a class implements ProviderInterface and matches the pattern
		 * @param string $className
		 * @param bool $debug
		 * @return ProviderInterface|null
		 */
		protected function checkClassIsProvider(string $className, bool $debug = false): ?ProviderInterface {
			// Skip already scanned classes
			if (isset($this->scannedClasses[$className])) {
				return null;
			}
			
			$this->scannedClasses[$className] = true;
			
			try {
				// Check if the class exists
				if (!class_exists($className)) {
					return null;
				}
				
				// Check if class name matches pattern (if pattern is set)
				if ($this->pattern !== null && !preg_match($this->pattern, $className)) {
					return null;
				}
				
				$reflectionClass = new ReflectionClass($className);
				
				// Skip abstract classes
				if ($reflectionClass->isAbstract()) {
					return null;
				}
				
				// Check if it implements ProviderInterface
				if (!$reflectionClass->implementsInterface(ProviderInterface::class)) {
					return null;
				}
				
				// Instantiate the provider
				return $reflectionClass->newInstance();
				
			} catch (\Throwable $e) {
				if ($debug) {
					echo "[ERROR] Failed to process class {$className}: {$e->getMessage()}\n";
				}
				return null;
			}
		}
		
		/**
		 * Get all PHP files in a directory recursively
		 * @param string $directory
		 * @return array<string>
		 */
		protected function getPhpFiles(string $directory): array {
			$files = [];
			
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST
			);
			
			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$files[] = $file->getPathname();
				}
			}
			
			return $files;
		}
		
		/**
		 * Auto-discover PSR-4 directories from composer.json
		 * This method reads the composer.json file and extracts all PSR-4 autoload directories.
		 * It's useful for finding all source directories that might contain classes.
		 * @param string|null $basePath Base path of the project, defaults to current working directory
		 * @param bool $debug Whether to output warnings for missing directories
		 * @return array<string> List of absolute paths to all PSR-4 directories
		 */
		public static function discoverPsr4Directories(?string $basePath = null, bool $debug = false): array {
			// Use the current working directory if no base path provided
			$basePath = $basePath ?? getcwd();
			$composerPath = $basePath . '/composer.json';
			
			// Return an empty array if composer.json doesn't exist
			if (!file_exists($composerPath)) {
				return [];
			}
			
			// Parse composer.json file
			$composer = json_decode(file_get_contents($composerPath), true);
			
			// Return an empty array if parsing failed or no PSR-4 configuration exists
			if (!$composer || !isset($composer['autoload']['psr-4'])) {
				return [];
			}
			
			// Process each PSR-4 namespace mapping
			$directories = [];

			foreach ($composer['autoload']['psr-4'] as $namespace => $paths) {
				// Convert single path string to array for consistent handling
				if (is_string($paths)) {
					$paths = [$paths];
				}
				
				// Process each path within the namespace
				foreach ($paths as $path) {
					// Build absolute path and ensure no trailing slash
					$fullPath = $basePath . '/' . rtrim($path, '/');
					
					// Only include directories that actually exist
					if (is_dir($fullPath)) {
						$directories[] = $fullPath;
					} elseif ($debug) {
						// Output warning for missing directories when in debug mode
						echo "[WARNING] PSR-4 directory not found: {$fullPath}\n";
					}
				}
			}
			
			return $directories;
		}
	}