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
		 * Get class annotations including inherited ones
		 * @param mixed $class The class object or class name to analyze
		 * @param string|null $annotationClass Optional filter to return only annotations of a specific class
		 * @param bool $includeInherited Whether to include parent class annotations (default: true)
		 * @return array
		 * @throws ParserException
		 */
		public function getClassAnnotations(mixed $class, ?string $annotationClass = null, bool $includeInherited = true): array {
			try {
				$reflection = new \ReflectionClass($class);
				
				// If not including inherited, just use the single class, otherwise get full chain
				if ($includeInherited) {
					$inheritanceChain = $this->getInheritanceChain($reflection);
				} else {
					$inheritanceChain = [$reflection];
				}
				
				// Process from parent to child (so child annotations can override)
				$allAnnotations = [];
				
				foreach ($inheritanceChain as $classInChain) {
					$annotations = $this->getAllObjectAnnotations($classInChain->getName());
					
					if (!empty($annotations['class'])) {
						// Merge annotations (child overrides parent)
						$allAnnotations = array_merge($allAnnotations, $annotations['class']);
					}
				}
				
				// Apply annotation class filter if provided
				if ($annotationClass !== null) {
					return array_filter($allAnnotations, function ($item) use ($annotationClass) {
						return $item instanceof $annotationClass;
					});
				}
				
				return $allAnnotations;
			} catch (\ReflectionException $e) {
				throw new ParserException($e->getMessage(), $e->getCode(), $e);
			}
		}
		
		/**
		 * Checks if a given entity class has a specific annotation.
		 * @param mixed $class            The object to check
		 * @param string $annotationClass The annotation class to look for
		 * @return bool                   True if the annotation exists on the property, false otherwise
		 */
		public function classHasAnnotation(mixed $class, string $annotationClass): bool {
			try {
				return !empty($this->getClassAnnotations($class, $annotationClass));
			} catch (ParserException $e) {
				return false;
			}
		}
		
		/**
		 * Takes a method's docComment and parses it to extract annotations
		 * @param mixed $class            The class object or class name to analyze
		 * @param string $methodName      The name of the method whose annotations to retrieve
		 * @param string|null $annotationClass Optional filter to return only annotations of a specific class
		 * @return array                  Array of parsed annotations for the specified method
		 * @throws ParserException        If there's an error parsing the annotations
		 */
		public function getMethodAnnotations(mixed $class, string $methodName, ?string $annotationClass=null): array {
			// Get all annotations for the method
			$annotations = $this->getAllObjectAnnotations($class);
			
			// If no annotations found, return an empty array
			if (!isset($annotations['methods'][$methodName])) {
				return [];
			}
			
			// If an annotation class filter is provided, only return annotations of that type
			if ($annotationClass !== null) {
				return array_filter($annotations['methods'][$methodName], function ($item) use ($annotationClass) {
					// Filter the method's annotations to include only instances of the specified class
					return $item instanceof $annotationClass;
				});
			}
			
			// Return all annotations for the specified method
			return $annotations["methods"][$methodName];
		}
		
		/**
		 * Checks if a method in a given entity class has a specific annotation.
		 * @param mixed $class            The object to check
		 * @param string $methodName      The name of the method to inspect for annotations
		 * @param string $annotationClass The annotation class to look for
		 * @return bool                   True if the annotation exists on the method, false otherwise
		 */
		public function methodHasAnnotation(mixed $class, string $methodName, string $annotationClass): bool {
			try {
				return !empty($this->getMethodAnnotations($class, $methodName, $annotationClass));
			} catch (ParserException $e) {
				return false;
			}
		}
		
		/**
		 * Takes a property's docComment and parses it
		 * @param mixed $class
		 * @param string $propertyName
		 * @return array
		 * @throws ParserException
		 */
		public function getPropertyAnnotations(mixed $class, string $propertyName, ?string $annotationClass=null): array {
			// Get all annotations for the property
			$annotations = $this->getAllObjectAnnotations($class);
			
			// If no annotations found, return an empty array
			if (!isset($annotations['properties'][$propertyName])) {
				return [];
			}
			
			// If an annotation class filter is provided, only return annotations of that type
			if ($annotationClass !== null) {
				return array_filter($annotations['properties'][$propertyName], function ($item) use ($annotationClass) {
					// Filter the method's annotations to include only instances of the specified class
					return $item instanceof $annotationClass;
				});
			}
			
			// Return all annotations for the specified method
			return $annotations["properties"][$propertyName] ?? [];
		}
		
		/**
		 * Checks if a method in a given entity class has a specific annotation.
		 * @param mixed $class            The object to check
		 * @param string $propertyName    The name of the property to inspect for annotations
		 * @param string $annotationClass The annotation class to look for
		 * @return bool                   True if the annotation exists on the property, false otherwise
		 */
		public function propertyHasAnnotation(mixed $class, string $propertyName, string $annotationClass): bool {
			try {
				return !empty($this->getPropertyAnnotations($class, $propertyName, $annotationClass));
			} catch (ParserException $e) {
				return false;
			}
		}
		
		/**
		 * Parses a string and returns the found annotations
		 * @param $string
		 * @return array
		 * @throws ParserException
		 */
		public function getAnnotations(string $string): array {
			try {
				$lexer = new Lexer($string);
				$parser = new Parser($lexer, $this->configuration);
				return $parser->parse();
			} catch (LexerException $e) {
				throw new ParserException($e->getMessage(), $e->getCode(), $e);
			}
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
			// Create the cache path
			$cachePath = "{$this->annotationCachePath}/{$cacheFilename}";
			
			// Get the file contents and deserialize
			return unserialize(file_get_contents($cachePath));
		}
		
		/**
		 * Updates the cache
		 * @param string $cacheFilename
		 * @param array $annotations
		 * @return void
		 */
		protected function writeCacheToFile(string $cacheFilename, array $annotations): void {
			// Ensure the cache directory exists before attempting to create files
			// This is important for first-time setup or when deploying to new environments
			if (!is_dir($this->annotationCachePath)) {
				// Create the directory structure recursively with standard permissions
				// 0755 allows the owner to read/write/execute and others to read/execute
				// The 'true' parameter creates parent directories as needed
				mkdir($this->annotationCachePath, 0755, true);
			}
			
			// Create the cache path
			$cachePath = $this->annotationCachePath . DIRECTORY_SEPARATOR . $cacheFilename;

			// Write the file to the path
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
		 * @param \ReflectionClass $reflection
		 * @return array
		 * @throws ParserException
		 */
		protected function readAllObjectAnnotations(\ReflectionClass $reflection): array {
			// Setup array which will receive the parse results
			$result = [
				'class'      => [],
				'properties' => [],
				'methods'    => []
			];
			
			// Load the use statements of this file
			$imports = $this->use_statement_parser->getImportsForClass($reflection);
			
			// Read the doc comment of the class
			$docComment = $reflection->getDocComment();
			
			// Parse the annotations inside these comments
			if (!empty($docComment)) {
				$result['class'] = $this->getAnnotationsWithImports($docComment, $imports);
			}
			
			// Parse the annotations and return result
			$this->parseAnnotations($reflection->getProperties(), $result['properties'], $imports);
			$this->parseAnnotations($reflection->getMethods(), $result['methods'], $imports);
			return $result;
		}
		
		/**
		 * Retrieve all annotations for a given class, caching the results for performance.
		 * @param mixed $class The fully qualified class name to get annotations for.
		 * @return array An array containing all annotations for the class, its properties, and its methods.
		 * @throws ParserException
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
					$this->cached_annotations[$cacheFilename] = $this->readAllObjectAnnotations($reflection);
					return $this->cached_annotations[$cacheFilename];
				}
				
				// Check if a valid cache file exists and is up to date
				// This compares file modification times to determine if cache is stale
				if ($this->cacheValid($className, $reflection)) {
					$this->cached_annotations[$cacheFilename] = $this->readCacheFromFile($cacheFilename);
					return $this->cached_annotations[$cacheFilename];
				}
				
				// If no valid cache exists, parse the annotations directly from the class
				$annotations = $this->readAllObjectAnnotations($reflection);
				
				// Write the newly parsed annotations to the cache file
				// This will speed up future requests for this class's annotations
				$this->writeCacheToFile($cacheFilename, $annotations);
				
				// Also store the annotations in memory cache for this request lifecycle
				$this->cached_annotations[$cacheFilename] = $annotations;
				
				// Return the annotations to the caller
				return $annotations;
			} catch (LexerException | \ReflectionException $e) {
				throw new ParserException($e->getMessage(), $e->getCode(), $e);
			}
		}
		
		/**
		 * Parses a string and returns the found annotations, with import resolution
		 * @param string $string The docblock to parse
		 * @param array $imports Map of aliases to fully qualified class names
		 * @return array
		 * @throws ParserException
		 */
		protected function getAnnotationsWithImports(string $string, array $imports): array {
			try {
				$lexer = new Lexer($string);
				$parser = new Parser($lexer, $this->configuration, $imports);
				return $parser->parse();
			} catch (LexerException $e) {
				throw new ParserException($e->getMessage(), $e->getCode(), $e);
			}
		}
		
		/**
		 * Get the full inheritance chain for a class (from parent to child)
		 * @param \ReflectionClass $reflection
		 * @return array Array of ReflectionClass objects from parent to child
		 */
		protected function getInheritanceChain(\ReflectionClass $reflection): array {
			$chain = [];
			$current = $reflection;
			
			// Walk up the inheritance chain
			while ($current !== false) {
				$chain[] = $current;
				$current = $current->getParentClass();
			}
			
			// Reverse to get parent-to-child order
			return array_reverse($chain);
		}
	}