<?php
	
	namespace Quellabs\AnnotationReader;
	
	use Quellabs\AnnotationReader\Exception\LexerException;
	use Quellabs\AnnotationReader\Exception\ParserException;
	use Quellabs\AnnotationReader\LexerParser\Lexer;
	use Quellabs\AnnotationReader\LexerParser\Parser;
	use Quellabs\AnnotationReader\LexerParser\UseStatementParser;
	
	class AnnotationReader {
		
		protected UseStatementParser $use_statement_parser;
		protected string $annotationCachePath;
		protected bool $useCache;
		protected array $configuration;
		protected array $cached_annotations;
		
		/**
		 * AnnotationReader constructor
		 */
		public function __construct(Configuration $configuration) {
			// Store annotation cache information
			$this->useCache = $configuration->useAnnotationCache();
			$this->annotationCachePath = $configuration->getAnnotationCachePath();
			
			// Instantiate use statement parser
			$this->use_statement_parser = new UseStatementParser();
			
			// Store the configuration array
			$this->configuration = [];
			
			// read cached data
			$this->cached_annotations = [];
		}
		
		/**
		 * Transforms a className to a filename
		 * @param string $className
		 * @return string
		 */
		protected function generateCacheFilename(string $className): string {
			return str_replace("\\", "#", $className) . ".cache";
		}
		
		/**
		 * Reads from cache
		 * @param string $cacheFilename
		 * @return array
		 */
		protected function readCacheFromFile(string $cacheFilename): array {
			$cachePath = "{$this->annotationCachePath}/{$cacheFilename}";
			return unserialize(file_get_contents($cachePath));
		}
		
		/**
		 * Updates the cache
		 * @param string $cacheFilename
		 * @param array $annotations
		 * @return void
		 */
		protected function writeCacheToFile(string $cacheFilename, array $annotations): void {
			$cachePath = "{$this->annotationCachePath}/{$cacheFilename}";
			file_put_contents($cachePath, serialize($annotations));
		}
		
		/**
		 * Validates whether the annotation cache for a class is still valid.
		 * @param string $cacheFilename The name of the cache file to check
		 * @param \ReflectionClass $reflection Reflection object for the class being cached
		 * @return bool Returns true if the cache is valid, false if it needs to be regenerated
		 */
		protected function cacheValid(string $cacheFilename, \ReflectionClass $reflection): bool {
			// Check if the cache file exists at all
			// If it doesn't exist, we need to generate the cache so return false
			if (!file_exists("{$this->annotationCachePath}/{$cacheFilename}")) {
				return false;
			}
			
			// Get the last modification time of the class file
			// This helps us determine if the class has been changed since cache creation
			$classCreateDate = filemtime($reflection->getFileName());
			
			// Get the last modification time of the cache file
			// This tells us when the cache was last generated
			$cacheCreateDate = filemtime("{$this->annotationCachePath}/{$cacheFilename}");
			
			// Compare timestamps to determine cache validity
			// Return true if the cache was created after the class was last modified
			// Return false if the class has been modified since the cache was created
			return $classCreateDate <= $cacheCreateDate;
		}
		
		/**
		 * Parse annotations for properties or methods and update the result array.
		 * @param array $items An array of ReflectionProperty or ReflectionMethod objects.
		 * @param array $result The result array to be updated with parsed annotations.
		 * @param array $imports The import list
		 * @return void
		 */
		protected function parseAnnotations(array $items, array &$result, array $imports): void {
			// Loop through each Reflection item (either property or method)
			foreach ($items as $item) {
				// Get the doc comment for the current item
				$docComment = $item->getDocComment();
				
				// Skip if there is no doc comment
				if (empty($docComment)) {
					continue;
				}
				
				// Retrieve annotations from the doc comment with imports
				$annotations = $this->getAnnotationsWithImports($docComment, $imports);
				
				// Skip if there are no annotations
				if (empty($annotations)) {
					continue;
				}
				
				// Add the annotations to the result array
				$result[$item->getName()] = $annotations;
			}
		}
		
		/**
		 * Fetch all object annotations
		 * @param mixed $class
		 * @return array
		 */
		protected function readAllObjectAnnotations(mixed $class): array {
			$result = [
				'class'      => [],
				'properties' => [],
				'methods'    => []
			];
			
			try {
				$reflection = new \ReflectionClass($class);
			} catch (\ReflectionException $e) {
				return $result;
			}
			
			// Load the use statements of this file
			$imports = $this->use_statement_parser->getImportsForClass($reflection);
			
			// Resolve the annotations with imports
			$result['class'] = $this->getAnnotationsWithImports($reflection->getDocComment(), $imports);
			
			// Parse the annotations and return result
			$this->parseAnnotations($reflection->getProperties(), $result['properties'], $imports);
			$this->parseAnnotations($reflection->getMethods(), $result['methods'], $imports);
			return $result;
		}
		
		/**
		 * Retrieve all annotations for a given class, caching the results for performance.
		 * @param mixed $class The fully qualified class name to get annotations for.
		 * @return array An array containing all annotations for the class, its properties, and its methods.
		 */
		protected function getAllObjectAnnotations(mixed $class): array {
			try {
				// Create a ReflectionClass object for the given class
				// This provides metadata about the class structure and properties
				$reflection = new \ReflectionClass($class);
				$className = $reflection->getName();
				
				// Generate a cache filename based on the class name
				// This provides a unique identifier for storing and retrieving cached annotations
				$cacheFilename = $this->generateCacheFilename($className);
				
				// Check if annotations for this class are already cached in memory
				// If so, return them immediately to avoid redundant processing
				if (isset($this->cached_annotations[$cacheFilename])) {
					return $this->cached_annotations[$cacheFilename];
				}
				
				// If caching is disabled or no cache path is set, process annotations directly
				// We still store in memory cache to avoid redundant processing within the same request
				if (!$this->useCache || empty($this->annotationCachePath)) {
					$this->cached_annotations[$cacheFilename] = $this->readAllObjectAnnotations($class);
					return $this->cached_annotations[$cacheFilename];
				}
				
				// Check if a valid cache file exists and is up to date
				// This compares file modification times to determine if cache is stale
				if ($this->cacheValid($className, $reflection)) {
					$this->cached_annotations[$cacheFilename] = $this->readCacheFromFile($cacheFilename);
					return $this->cached_annotations[$cacheFilename];
				}
				
				// If no valid cache exists, parse the annotations directly from the class
				$annotations = $this->readAllObjectAnnotations($class);
				
				// Write the newly parsed annotations to the cache file
				// This will speed up future requests for this class's annotations
				$this->writeCacheToFile($cacheFilename, $annotations);
				
				// Also store the annotations in memory cache for this request lifecycle
				$this->cached_annotations[$cacheFilename] = $annotations;
				
				// Return the annotations to the caller
				return $annotations;
			} catch (\ReflectionException $e) {
				// If reflection fails (e.g., class doesn't exist), return an empty array
				// This provides graceful error handling rather than throwing exceptions
				return [];
			}
		}
		
		/**
		 * Parses a string and returns the found annotations, with import resolution
		 * @param string $string The docblock to parse
		 * @param array $imports Map of aliases to fully qualified class names
		 * @param string|null &$errorMessage Optional error message reference
		 * @return array
		 */
		protected function getAnnotationsWithImports(string $string, array $imports, ?string &$errorMessage=null): array {
			try {
				$lexer = new Lexer($string);
				$parser = new Parser($lexer, $this->configuration, $imports);
				return $parser->parse();
			} catch (LexerException | ParserException $e) {
				if ($errorMessage !== null) {
					$errorMessage = $e->getMessage();
				}
				
				return [];
			}
		}
		
		/**
		 * Takes a class's docComment and parses it
		 * @param mixed $class
		 * @return array
		 */
		public function getClassAnnotations(mixed $class): array {
			$annotations = $this->getAllObjectAnnotations($class);
			return $annotations["class"] ?? [];
		}
		
		/**
		 * Takes a method's docComment and parses it
		 * @param mixed $class
		 * @param string $method
		 * @return array
		 */
		public function getMethodAnnotations(mixed $class, string $method): array {
			$annotations = $this->getAllObjectAnnotations($class);
			return $annotations["methods"][$method] ?? [];
		}
		
		/**
		 * Takes a property's docComment and parses it
		 * @param mixed $class
		 * @param string $property
		 * @return array
		 */
		public function getPropertyAnnotations(mixed $class, string $property): array {
			$annotations = $this->getAllObjectAnnotations($class);
			return $annotations["properties"][$property] ?? [];
		}
		
		/**
		 * Parses a string and returns the found annotations
		 * @param $string
		 * @param string|null &$errorMessage Optional error message reference
		 * @return array
		 */
		public function getAnnotations($string, ?string &$errorMessage=null): array {
			try {
				$lexer = new Lexer($string);
				$parser = new Parser($lexer, $this->configuration);
				return $parser->parse();
			} catch (LexerException | ParserException $e) {
				if ($errorMessage !== null) {
					$errorMessage = $e->getMessage();
				}
				
				return [];
			}
		}
	}