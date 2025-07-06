<?php
	
	namespace Quellabs\Discover\Utilities;
	
	/**
	 * Handles loading and parsing Composer's installed packages data
	 * Supports both modern PHP format (installed.php) and legacy JSON format (installed.json)
	 * Uses PSR4 utility class for file path resolution
	 */
	class ComposerInstalledLoader {
		
		/**
		 * @var PSR4 Path resolution utility
		 */
		private PSR4 $pathResolver;
		
		/**
		 * @var array<string, array|null> Cache of parsed installed files
		 */
		private array $installedDataCache = [];
		
		/**
		 * Directory to start searching from (defaults to current directory)
		 * @var string|null
		 */
		private ?string $startDirectory;
		
		/**
		 * Constructor
		 * @param PSR4|null $pathResolver Optional PSR4 instance (creates new one if not provided)
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 */
		public function __construct(
			?PSR4 $pathResolver = null,
			?string $startDirectory = null
		) {
			$this->pathResolver = $pathResolver ?? new PSR4();
			$this->startDirectory = $startDirectory;
		}
		
		/**
		 * Parse and return installed packages data with caching
		 * Automatically handles both PHP and JSON formats, preferring PHP
		 * @return array|null Parsed installed packages data or null on failure
		 */
		public function getData(): ?array {
			// Try PHP format first (modern Composer 2.1+)
			// PHP format is preferred as it's faster to load and parse
			$lockfilePath = $this->pathResolver->getComposerLockFilePath($this->startDirectory);
			
			if ($lockfilePath !== null) {
				// Found installed.php file, attempt to load and parse it
				return $this->installedDataCache[$phpPath] = $this->parseLockFile($lockfilePath);
			}
			
			// Fallback to JSON format (legacy Composer)
			// JSON format is used by older Composer versions or when lockfile is unavailable
			$jsonPath = $this->pathResolver->getComposerInstalledJsonPath($this->startDirectory);
			
			if ($jsonPath !== null) {
				// Found installed.json file, attempt to load and parse it
				return $this->installedDataCache[$jsonPath] = $this->parseJsonFile($jsonPath);
			}
			
			// No installed packages file found in either format
			return null;
		}
		
		/**
		 * Parse a JSON file and return its contents as an array
		 * @param string $filePath Path to the JSON file
		 * @return array|null Parsed JSON data or null on failure
		 */
		protected function parseJsonFile(string $filePath): ?array {
			// Check if the file exists and is readable
			if (!is_readable($filePath)) {
				return null;
			}
			
			// Read the entire file contents into a string
			$content = file_get_contents($filePath);
			
			// Check if file reading was successful
			if ($content === false) {
				return null;
			}
			
			// Decode the JSON string into a PHP array
			// The second parameter 'true' ensures we get an associative array instead of objects
			$data = json_decode($content, true);
			
			// Check if JSON parsing was successful by examining the last JSON error
			if (json_last_error() !== JSON_ERROR_NONE) {
				return null;
			}
			
			// Return everything in packages if that key is present
			if (isset($data['packages'])) {
				return $data['packages'];
			}
			
			// Otherwise, return the parsed data as-is
			return $data;
		}
	}