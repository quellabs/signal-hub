<?php
	
	namespace Quellabs\AnnotationReader\LexerParser;
	
	use Quellabs\AnnotationReader\Exception\LexerException;
	use Quellabs\AnnotationReader\Exception\ParserException;
	
	class Parser {

		// Default keys used throughout the parser
		private const DEFAULT_VALUE_KEY = 'value';
		private const SWAGGER_PREFIX = 'OA\\';
		
		/** @var Lexer The lexer instance for tokenizing input */
		private Lexer $lexer;
		
		/** @var array List of annotation names to ignore during parsing */
		private array $ignore_annotations;
		
		/** @var array Fast lookup set for ignored annotations (using array keys for O(1) lookup) */
		private array $ignore_annotations_set;
		
		/**
		 * @var array<string, array{segments: string[], fqcn: string, lastPart: string, namespace: string}>
		 * Preprocessed import data for faster class resolution
		 */
		private array $preprocessed_imports = [];
		
		/**
		 * @var array<string, string[]>
		 * Reverse mapping from namespace segments to full namespaces for quick lookups
		 */
		private array $namespace_map = [];
		
		/**
		 * @var array<string, string> Map of aliases to fully qualified class names
		 */
		private array $imports = [];
		
		/**
		 * @var array Cache for resolved class references to avoid repeated resolution
		 */
		private array $classReferenceCache = [];
		
		/**
		 * @var array<string, mixed> Configuration array for resolving configuration placeholders
		 */
		private array $configuration;
		
		/**
		 * Parser constructor.
		 * @param Lexer $lexer The lexer instance for tokenizing input
		 * @param array<string, mixed> $configuration Configuration array for placeholder resolution
		 * @param array<string, string> $imports Optional map of aliases to fully qualified class names
		 */
		public function __construct(Lexer $lexer, array $configuration=[], array $imports = []) {
			// Store the lexer instance for token processing
			$this->lexer = $lexer;
			
			// Store configuration for resolving ${config.key} placeholders
			$this->configuration = $configuration;
			
			// Store import mappings for class resolution
			$this->imports = $imports;
			
			// Define standard PHP doc annotations that should be ignored during parsing
			$this->ignore_annotations = [
				'param',      // Method parameter documentation
				'return',     // Return type documentation
				'var',        // Variable type documentation
				'type',       // Type hint documentation
				'throws',     // Exception documentation
				'todo',       // TODO comments
				'fixme',      // FIXME comments
				'author',     // Author information
				'copyright',  // Copyright information
				'license',    // License information
				'package',    // Package information
				'template',   // Template documentation
				'url',        // URL references
				'note',       // General notes
				'deprecated', // Deprecation notices
				'since',      // Version since information
				'see',        // See also references
				'example',    // Example code
				'inheritdoc', // Inherit documentation
				'internal',   // Internal use only
				'api',        // API documentation
				'version',    // Version information
				'category'    // Category classification
			];
			
			// Create a fast lookup set from ignored annotations (O(1) instead of O(n) lookups)
			$this->ignore_annotations_set = array_flip($this->ignore_annotations);
			
			// Preprocess imports for faster class resolution during parsing
			$this->preprocessImports();
		}
		
		/**
		 * Preprocess imports for faster class resolution
		 */
		private function preprocessImports(): void {
			foreach ($this->imports as $alias => $fqcn) {
				// Split each import into segments for easier matching
				$segments = explode('\\', $fqcn);
				
				$this->preprocessed_imports[$alias] = [
					'segments'  => $segments,
					'fqcn'      => $fqcn,
					'lastPart'  => end($segments),
					'namespace' => implode('\\', array_slice($segments, 0, -1))
				];
				
				// Create reverse mapping for namespace lookups
				foreach ($segments as $index => $segment) {
					$partialNamespace = implode('\\', array_slice($segments, 0, $index + 1));
					$this->namespace_map[$segment][] = $partialNamespace;
				}
			}
		}
		
		/**
		 * Parses a configuration key in the format {parameter.parameter.parameter}
		 * converting it into a dot-notation string like "parameter.parameter.parameter"
		 * @return string The parsed configuration key in dot notation
		 * @throws LexerException
		 */
		protected function parseConfigurationKey(): string {
			// Match the opening curly brace that starts the configuration key
			$this->lexer->match(Token::CurlyBraceOpen);
			
			// Initialize an empty string to build the configuration key
			$key = "";
			
			do {
				// If this isn't the first parameter, add a dot separator
				if (!empty($key)) {
					$key .= ".";
				}
				
				// Match and get the next parameter token in the sequence
				$parameter = $this->lexer->match(Token::Parameter);
				
				// Append the parameter value to our building key
				$key .= $parameter->getValue();
			} while ($this->lexer->optionalMatch(Token::Dot)); // Continue if there's a dot, indicating more parameters
			
			// Match the closing curly brace that ends the configuration key
			$this->lexer->match(Token::CurlyBraceClose);
			
			// Return the fully constructed dot-notation configuration key
			return $key;
		}
		
		/**
		 * Parses a value from the token stream based on its type
		 * @param Token $token The token to parse
		 * @return mixed The parsed value (array, string, number, boolean, or null)
		 * @throws LexerException|ParserException|\ReflectionException
		 */
		protected function parseValue(Token $token): mixed {
			// Handle a configuration string (e.g. ${config.cache.default_ttl})
			if ($this->lexer->optionalMatch(Token::Dollar)) {
				$configKey = $this->parseConfigurationKey();
				return $this->resolveNestedValue($configKey);
			}
			
			// Handle a JSON string
			if ($this->lexer->optionalMatch(Token::CurlyBraceOpen)) {
				$value = $this->parseAttributeList();
				$this->lexer->match(Token::CurlyBraceClose);
				return $value;
			}
			
			// Handle string or number literals
			if ($this->lexer->optionalMatch(Token::String, $token) || $this->lexer->optionalMatch(Token::Number, $token)) {
				// Return the actual value of the token
				return $token->getValue();
			}
			
			// Handle negative numbers (e.g., -10)
			if ($this->lexer->peek()->getType() == Token::Minus) {
				// Consume the minus token
				$this->lexer->match(Token::Minus);
				
				// Get the number token that follows the minus
				$token = $this->lexer->match(Token::Number);
				
				// Return the negative value
				return 0 - $token->getValue();
			}
			
			// Handle boolean true value
			if ($this->lexer->optionalMatch(Token::True)) {
				return true;
			}
			
			// Handle boolean false value
			if ($this->lexer->optionalMatch(Token::False)) {
				return false;
			}
			
			// Default case: return null if no other value type matches
			return null;
		}
		
		/**
		 * Helper for parseJson
		 * @return mixed
		 * @throws LexerException|ParserException|\ReflectionException
		 */
		protected function parseAttributeValue(): mixed {
			$parameterValue = new Token();
			
			if ($this->lexer->optionalMatch(Token::CurlyBraceOpen)) {
				$value = $this->parseAttributeList();
				$this->lexer->match(Token::CurlyBraceClose);
			} elseif ($this->lexer->optionalMatch(Token::String, $parameterValue) || $this->lexer->optionalMatch(Token::Number, $parameterValue)) {
				$value = $parameterValue->getValue();
			} elseif ($this->lexer->optionalMatch(Token::Minus)) {
				$token = $this->lexer->match(Token::Number);
				$value = 0 - $token->getValue();
			} elseif ($this->lexer->optionalMatch(Token::True)) {
				$value = true;
			} elseif ($this->lexer->optionalMatch(Token::False)) {
				$value = false;
			} else {
				$value = null;
			}
			
			return $value;
		}
		
		/**
		 * Parses an attribute list into an associative array
		 * Can handle annotations, key-value pairs (key=value), and simple value arrays
		 * @return array The parsed attributes as an associative or indexed array
		 * @throws LexerException If tokenization fails
		 * @throws ParserException If the attribute structure is invalid
		 * @throws \ReflectionException If annotation reflection fails
		 */
		protected function parseAttributeList(): array {
			// Initialize an empty array to store the parsed attributes
			$attributes = [];
			
			do {
				// Declare variables before use
				$annotationToken = null;
				$attributeKey = null;
				
				// Handle annotation syntax (@AnnotationName)
				if ($this->lexer->optionalMatch(Token::Annotation, $annotationToken)) {
					// Parse the annotation and store it using its class name as the key
					$annotation = $this->parseAnnotation($annotationToken);
					
					$attributes[get_class($annotation)] = $annotation;
					
					continue;
				}
				
				// Try to match a string token for the attribute key
				if (!$this->lexer->optionalMatch(Token::String, $attributeKey)) {
					// If not a string, try to match a number token
					if (!$this->lexer->optionalMatch(Token::Number, $attributeKey)) {
						// If neither string nor number, throw an exception
						throw new ParserException("Expected number or string, got " . $attributeKey->toString($attributeKey->getType()));
					}
				}
				
				// Check if this is a key-value pair (has an equals sign)
				if (!$this->lexer->optionalMatch(Token::Equals)) {
					// No equals sign found, treat as a simple array value
					$attributes[] = $attributeKey->getValue();
					continue;
				}
				
				// Parse the value part of the key-value pair
				$value = $this->parseAttributeValue();
				
				// Ensure the value is valid
				if ($value === null) {
					throw new ParserException("Invalid value type");
				}
				
				// Add the key-value pair to the attributes array
				$attributes[$attributeKey->getValue()] = $value;
			} while ($this->lexer->optionalMatch(Token::Comma)); // Continue if there's a comma, indicating more attributes
			
			// Return the complete attributes array
			return $attributes;
		}
		
		/**
		 * Parse a string of parameters
		 * @return array
		 * @throws LexerException|ParserException|\ReflectionException
		 */
		protected function parseParameters(): array {
			$parameters = [];
			
			do {
				$parameterKey = new Token();
				$parameterValue = new Token();
				
				// Handle named parameter case
				if ($this->lexer->optionalMatch(Token::Parameter, $parameterKey)) {
					// Skip if no equals sign follows the parameter name
					if (!$this->lexer->optionalMatch(Token::Equals)) {
						continue;
					}
					
					// Parse the value
					$value = $this->parseValue($parameterValue);
					
					// Early failure if value parsing failed
					if ($value === null) {
						throw new ParserException("Expected number or string, got " . $parameterValue->toString($parameterValue->getType()));
					}
					
					$parameters[$parameterKey->getValue()] = $value;
					continue;
				}
				
				// Handle an unnamed parameter case
				$value = $this->parseValue($parameterKey);
				
				// Skip if value parsing failed
				if ($value === null) {
					continue;
				}
				
				$parameters[self::DEFAULT_VALUE_KEY] = $value;
				
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			return $parameters;
		}
		
		/**
		 * Parses an annotation
		 * @param Token $token
		 * @return object
		 * @throws LexerException
		 * @throws ParserException
		 * @throws \ReflectionException
		 */
		protected function parseAnnotation(Token $token): object {
			// Fetch the annotation class name
			$value = $token->getValue();
			
			// Resolve the annotation class name using imports
			$tokenName = $this->resolveClassName($value);
			
			// Check if the class exists
			if (!class_exists($tokenName)) {
				throw new ParserException("Annotation class not found: {$tokenName}");
			}
			
			// Parse the parameters or use an empty array
			$parameters = [];
			
			if ($this->lexer->optionalMatch(Token::ParenthesesOpen)) {
				$parameters = $this->parseParameters();
				$this->lexer->match(Token::ParenthesesClose);
			}
			
			return new $tokenName($parameters);
		}
		
		/**
		 * Get a value from a nested array using dot notation
		 * @param string $path The dot notation path (e.g. "config.cache.default_ttl")
		 * @param string $default Value to return if the path doesn't exist
		 * @return mixed The value found at the path or the default
		 */
		protected function resolveNestedValue(string $path, string $default = ''): mixed {
			// Split the path into individual keys
			$keys = explode('.', $path);
			$current = $this->configuration;
			
			// Traverse the array using each segment of the path
			foreach ($keys as $key) {
				if (!is_array($current) || !array_key_exists($key, $current)) {
					return $default;
				}
				
				$current = $current[$key];
			}
			
			return $current;
		}
		
		/**
		 * Resolves a partially qualified class name against imported namespaces.
		 * Caches results for faster repeated lookups.
		 * @param string $className The class name to resolve (e.g., "Validation\Type")
		 * @return string Fully qualified class name if resolved, otherwise original class name.
		 */
		private function resolveClassReference(string $className): string {
			// Return cached data if available
			if (isset($this->classReferenceCache[$className])) {
				return $this->classReferenceCache[$className];
			}
			
			// If the class name does not contain a namespace separator, no resolution needed
			if (!str_contains($className, '\\')) {
				return $this->classReferenceCache[$className] = $className;
			}
			
			// Fetch parts information
			$parts = explode('\\', $className);
			$firstPart = $parts[0];
			$lastPart = end($parts);

			// 1. Look for direct alias match with the first part
			if (isset($this->preprocessed_imports[$firstPart])) {
				$import = $this->preprocessed_imports[$firstPart];
				$candidateClass = $import['namespace'] . '\\' . $className;
				
				if (class_exists($candidateClass) || interface_exists($candidateClass)) {
					return $this->classReferenceCache[$className] = $candidateClass;
				}
			}
			
			// 2. Look for imports that contain the first part in their segments
			foreach ($this->preprocessed_imports as $import) {
				$position = array_search($firstPart, $import['segments'], true);
				
				if ($position !== false) {
					$baseNamespace = implode('\\', array_slice($import['segments'], 0, $position));
					$candidateClass = $baseNamespace . '\\' . $className;
					
					if (class_exists($candidateClass) || interface_exists($candidateClass)) {
						return $this->classReferenceCache[$className] = $candidateClass;
					}
				}
			}
			
			// 3. Try to match the last part with imported classes that contain the first part
			foreach ($this->preprocessed_imports as $import) {
				if ($import['lastPart'] === $lastPart &&
					in_array($firstPart, $import['segments'], true)) {
					return $this->classReferenceCache[$className] = $import['fqcn'];
				}
			}
			
			// 4. Try namespace-based resolution using the preprocessed namespace map
			if (isset($this->namespace_map[$firstPart])) {
				foreach ($this->namespace_map[$firstPart] as $namespace) {
					$candidateClass = $namespace . '\\' . implode('\\', array_slice($parts, 1));
					
					if (class_exists($candidateClass) || interface_exists($candidateClass)) {
						return $this->classReferenceCache[$className] = $candidateClass;
					}
				}
			}
			
			return $this->classReferenceCache[$className] = $className;
		}
		
		/**
		 * Resolve a class name using the imports
		 * @param string $className Name or alias of the class
		 * @return string Fully qualified class name
		 */
		protected function resolveClassName(string $className): string {
			// Already fully qualified
			if (str_starts_with($className, '\\')) {
				return substr($className, 1); // Remove the leading backslash
			}
			
			// Check if it's an imported alias
			if (isset($this->imports[$className])) {
				return $this->imports[$className];
			}
			
			// For compound names like Alias\SubClass, resolve the alias part
			if (str_contains($className, '\\')) {
				$parts = explode('\\', $className, 2);
				$alias = $parts[0];
				$rest = $parts[1];
				
				// Direct match for the first part
				if (isset($this->imports[$alias])) {
					return $this->imports[$alias] . '\\' . $rest;
				}
				
				// Try our generic partial namespace resolver
				$resolved = $this->resolveClassReference($className);
				
				// Remove trailing slash if present
				if (str_starts_with($resolved, '\\')) {
					$resolved = substr($resolved, 1);
				}
				
				// Return the fully resolved path
				if ($resolved !== $className) {
					return $resolved;
				}
			}
			
			// Return as is if nothing matched
			return $className;
		}

		/**
		 * Parse the Docblock
		 * @return array
		 * @throws LexerException|ParserException
		 */
		public function parse(): array {
			try {
				$result = [];
				$token = $this->lexer->get();
				
				while ($token->getType() !== Token::Eof) {
					// Skip non-annotation tokens
					if ($token->getType() !== Token::Annotation) {
						$token = $this->lexer->get();
						continue;
					}
					
					// Skip ignored annotations
					$value = $token->getValue();
					
					if (isset($this->ignore_annotations_set[$value])) {
						$token = $this->lexer->get();
						continue;
					}
					
					// Skip swagger docs
					if (str_starts_with($value, self::SWAGGER_PREFIX)) {
						$token = $this->lexer->get();
						continue;
					}
					
					// Parse the annotation
					$annotation = $this->parseAnnotation($token);
					
					// Add the annotation to the result
					$result[get_class($annotation)] = $annotation;
					
					// Get the next token
					$token = $this->lexer->get();
				}
				
				return $result;
			} catch (\ReflectionException $e) {
				throw new ParserException("Reflection error: {$e->getMessage()}", $e->getCode(), $e);
			}
		}
	}