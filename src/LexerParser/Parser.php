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
				'url'
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
				
				if ($this->lexer->optionalMatch(Token::Parameter, $parameterKey)) {
					if (!$this->lexer->optionalMatch(Token::Equals)) {
						continue;
					}
					
					$value = $this->parseValue($parameterValue);
					
					if ($value === null) {
						throw new ParserException("Expected number or string, got " . $parameterValue->toString($parameterValue->getType()));
					}
					
					$parameters[$parameterKey->getValue()] = $value;
				} else {
					$value = $this->parseValue($parameterKey);
					
					if ($value !== null) {
						$parameters["value"] = $value;
					}
				}
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
				
				if (isset($this->imports[$alias])) {
					return $this->imports[$alias] . '\\' . $rest;
				}
				
				// If it contains namespace separators but doesn't match any import,
				// assume it's a fully qualified class name relative to the global namespace
				return $className;
			}
			
			// If we reach here, it's a simple class name with no namespace
			// Return as is - will be looked up in default namespaces
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
					if ($token->getType() == Token::Annotation) {
						$value = $token->getValue();
						
						if (!in_array($value, $this->ignore_annotations)) {
							// skip swagger docs
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
							
							// Parse the parameters and gather the result
							if ($this->lexer->optionalMatch(Token::ParenthesesOpen)) {
								$parameters = $this->parseParameters();
								$this->lexer->match(Token::ParenthesesClose);
								$result[$value] = new $tokenName($parameters);
							} else {
								$result[$value] = new $tokenName([]);
							}
						}
					}
					
					$token = $this->lexer->get();
				}
				
				return $result;
			} catch (\ReflectionException $e) {
				throw new ParserException("Reflection error: {$e->getMessage()}", $e->getCode(), $e);
			}
		}
	}