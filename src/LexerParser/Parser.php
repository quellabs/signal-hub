<?php
	
	namespace Quellabs\AnnotationReader\LexerParser;
	
	use Quellabs\AnnotationReader\Exception\LexerException;
	use Quellabs\AnnotationReader\Exception\ParserException;
	
	class Parser {
		
		protected Lexer $lexer;
		protected array $ignore_annotations;
		
		/**
		 * @var array<string, string> Map of aliases to fully qualified class names
		 */
		protected array $imports = [];

		/**
		 * @var array Cache property
		 */
		private array $classReferenceCache = [];
		
		/**
		 * @var array<string, mixed> Configuration array
		 */
		protected array $configuration;
		
		/**
		 * Parser constructor.
		 * @param Lexer $lexer
		 * @param array<string, string> $imports Optional map of aliases to fully qualified class names
		 */
		public function __construct(Lexer $lexer, array $configuration=[], array $imports = []) {
			$this->lexer = $lexer;
			$this->configuration = $configuration;
			$this->imports = $imports;
			$this->ignore_annotations = [
				'param',
				'return',
				'var',
				'type',
				'throws',
				'todo',
				'fixme',
				'author',
				'copyright',
				'license',
				'package',
				'template',
				'url',
				'note',
				'deprecated',
				'since',
				'see',
				'example',
				'inheritdoc',
				'internal',
				'api',
				'version',
				'category'
			];
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
				$value = $this->parseJson();
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
		protected function parseJsonValue(): mixed {
			$parameterValue = new Token();
			
			if ($this->lexer->optionalMatch(Token::CurlyBraceOpen)) {
				$value = $this->parseJson();
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
		 * Parses a JSON-like structure into an associative array
		 * Can handle both key-value pairs and simple value arrays
		 * @return array The parsed JSON data as an associative or indexed array
		 * @throws LexerException If the JSON structure is invalid
		 * @throws ParserException If the JSON structure is invalid
		 * @throws \ReflectionException
		 */
		protected function parseJson(): array {
			// Initialize an empty array to store the parsed parameters
			$parameters = [];
			
			do {
				// Create a new token to store the parameter key
				$parameterKey = new Token();
				
				// Try to match a string token for the parameter key
				if (!$this->lexer->optionalMatch(Token::String, $parameterKey)) {
					// If not a string, try to match a number token
					if (!$this->lexer->optionalMatch(Token::Number, $parameterKey)) {
						// If neither string nor number, throw an exception
						throw new ParserException("Expected number or string, got " . $parameterKey->toString($parameterKey->getType()));
					}
				}
				
				// Check if this is a key-value pair (has an equals sign)
				if (!$this->lexer->optionalMatch(Token::Equals)) {
					// No equals sign found, treat as a simple array value
					$parameters[] = $parameterKey->getValue();
					continue;
				}
				
				// Parse the value part of the key-value pair
				$value = $this->parseJsonValue();
				
				// Ensure the value is valid
				if ($value === null) {
					throw new ParserException("Invalid value type");
				}
				
				// Add the key-value pair to the parameter array
				$parameters[$parameterKey->getValue()] = $value;
			} while ($this->lexer->optionalMatch(Token::Comma)); // Continue if there's a comma, indicating more parameters
			
			// Return the complete parameters array
			return $parameters;
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
					
					$value = $this->parseValue($parameterValue);
					
					// Early failure if value parsing failed
					if ($value === null) {
						throw new ParserException("Expected number or string, got " . $parameterValue->toString($parameterValue->getType()));
					}
					
					$parameters[$parameterKey->getValue()] = $value;
					continue;
				}
				
				// Handle unnamed parameter case
				$value = $this->parseValue($parameterKey);
				
				// Skip if value parsing failed
				if ($value === null) {
					continue;
				}
				
				$parameters["value"] = $value;
				
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			return $parameters;
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
			
			// Preprocess imports once: explode all into segments
			$parts = explode('\\', $className);
			$firstPart = $parts[0];
			$lastPart = end($parts);
			
			$importSegments = array_map(function ($importedClass) {
				return explode('\\', $importedClass);
			}, $this->imports);
			
			// 1. Look for an import that contains the first part
			foreach ($importSegments as $alias => $segments) {
				$position = array_search($firstPart, $segments, true);
				
				if ($position !== false) {
					$baseNamespace = implode('\\', array_slice($segments, 0, $position));
					$candidateClass = $baseNamespace . '\\' . $className;
					
					if (class_exists($candidateClass) || interface_exists($candidateClass)) {
						return $this->classReferenceCache[$className] = $candidateClass;
					}
				}
			}
			
			// 2. Try to match the last part with imported classes that contain the first part
			foreach ($this->imports as $importedClass) {
				if (str_ends_with($importedClass, '\\' . $lastPart) && str_contains($importedClass, '\\' . $firstPart . '\\')) {
					return $this->classReferenceCache[$className] = $importedClass;
				}
			}
			
			// 3. Build a list of parent namespaces
			$potentialNamespaces = [];
			
			foreach ($importSegments as $segments) {
				array_pop($segments); // Remove the class name
				$currentNamespace = '';
				
				foreach ($segments as $segment) {
					$currentNamespace .= ($currentNamespace ? '\\' : '') . $segment;
					$potentialNamespaces[] = $currentNamespace;
				}
			}
			
			// Sort by length descending so deeper namespaces are tried first
			usort($potentialNamespaces, fn($a, $b) => strlen($b) <=> strlen($a));
			
			foreach ($potentialNamespaces as $namespace) {
				$candidateClass = $namespace . '\\' . $className;
				
				if (class_exists($candidateClass) || interface_exists($candidateClass)) {
					return $this->classReferenceCache[$className] = $candidateClass;
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
					
					if (in_array($value, $this->ignore_annotations)) {
						$token = $this->lexer->get();
						continue;
					}
					
					// Skip swagger docs
					if (str_starts_with($value, "OA\\")) {
						$token = $this->lexer->get();
						continue;
					}
					
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
					
					$result[$tokenName] = new $tokenName($parameters);
					$token = $this->lexer->get();
				}
				
				return $result;
			} catch (\ReflectionException $e) {
				throw new ParserException("Reflection error: {$e->getMessage()}", $e->getCode(), $e);
			}
		}
	}