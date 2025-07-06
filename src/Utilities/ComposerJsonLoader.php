<?php
	
	namespace Quellabs\Discover\Utilities;
	
	/**
	 * Handles loading and parsing the main composer.json configuration file
	 * Uses PSR4 utility class for file path resolution
	 */
	class ComposerJsonLoader {
		
		/**
		 * @var ComposerPathResolver Path resolution utility
		 */
		private ComposerPathResolver $pathResolver;
		
		/**
		 * @var array|null Cached composer.json data
		 */
		private ?array $composerJsonCache = null;
		
		/**
		 * Constructor
		 * @param ComposerPathResolver|null $pathResolver Optional PSR4 instance (creates new one if not provided)
		 */
		public function __construct(?ComposerPathResolver $pathResolver = null) {
			$this->pathResolver = $pathResolver ?? new ComposerPathResolver();
		}
		
		/**
		 * Parse and return composer.json data with caching
		 * @param string|null $startDirectory Directory to start searching from (defaults to current directory)
		 * @return array|null Parsed composer.json data or null on failure
		 */
		public function getData(?string $startDirectory = null): ?array {
			// Return cached result if available
			if ($this->composerJsonCache !== null) {
				return $this->composerJsonCache;
			}
			
			// Use PSR4 to locate the composer.json file
			$composerJsonPath = $this->pathResolver->getComposerJsonFilePath($startDirectory);
			
			if ($composerJsonPath === null) {
				return null;
			}
			
			// Parse and cache the result
			return $this->composerJsonCache = $this->parseJsonFile($composerJsonPath);
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
			
			// Validate the existence of an 'extra' section
			if (empty($data['extra'])) {
				return [];
			}
			
			// Return the extra section
			return $data['extra'];
		}
	}