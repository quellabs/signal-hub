<?php
	
	namespace Quellabs\Discover\Scanner;
	
	use Quellabs\Discover\Provider\ProviderDefinition;
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
		 * Scan directories for classes that implement ProviderInterface
		 * @return array<ProviderDefinition> Array of provider definitions
		 */
		public function scan(): array {
			// Get the configured directories to scan
			$dirs = $this->directories;
			
			// Scan each directory and merge the results
			$providerData = [];

			foreach ($dirs as $directory) {
				// Scan individual directory and combine results with existing discoveries
				// Each scanDirectory call returns an array of ProviderDefinition objects
				$providerData = array_merge($providerData, $this->scanDirectory($directory));
			}
			
			// Return all discovered provider definitions across all directories
			return $providerData;
		}
		
		/**
		 * This function traverses a directory structure, identifies all PHP files,
		 * attempts to extract class names from them, and checks if each class implements
		 * the ProviderInterface. All valid provider class data is returned.
		 * @param string $directory The root directory path to begin scanning
		 * @return array Array of provider data with class and family information
		 */
		protected function scanDirectory(string $directory): array {
			// Verify the directory exists and is accessible before attempting to scan
			if (!is_dir($directory) || !is_readable($directory)) {
				return [];
			}
			
			// Fetch all provider classes found in the directory
			$classes = $this->utilities->findClassesInDirectory($directory, function($className) {
				return $this->isValidProviderClass($className);
			});
			
			// Process each valid class found in the directory structure
			$definitions = [];
			
			foreach ($classes as $className) {
				$definitions[] = new ProviderDefinition(
					className: $className,
					family: $this->defaultFamily,
					configFile: null,
					metadata: $className::getMetadata(),
					defaults: $className::getDefaults()
				);
			}
			
			// Return all discovered provider class data from the directory
			return $definitions;
		}

		/**
		 * Check if a class implements ProviderInterface and matches the pattern
		 * @param string $className Fully qualified class name to check
		 * @return bool True if the class is a valid provider, false otherwise
		 */
		protected function isValidProviderClass(string $className): bool {
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
				return false;
			}
		}
	}