<?php
	
	namespace Quellabs\ObjectQuel\AnnotationsReader;
	
	class Parser {
		
		protected Lexer $lexer;
		protected array $ignore_annotations;
		
		/**
		 * @var array<string, string> Map of aliases to fully qualified class names
		 */
		protected array $imports = [];
		
		/**
		 * Parser constructor.
		 * @param Lexer $lexer
		 * @param array<string, string> $imports Optional map of aliases to fully qualified class names
		 */
		public function __construct(Lexer $lexer, array $imports = []) {
			$this->lexer = $lexer;
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
		 * Set the imports for annotation resolution
		 *
		 * @param array<string, string> $imports Map of aliases to fully qualified class names
		 * @return self
		 */
		public function setImports(array $imports): self {
			$this->imports = $imports;
			return $this;
		}
		
		/**
		 * Helper for parseParameters
		 * @param Token $token
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseValue(Token $token) {
			if ($this->lexer->optionalMatch(Token::CurlyBraceOpen)) {
				$value = $this->parseJson();
				$this->lexer->match(Token::CurlyBraceClose);
				return $value;
			}
			
			if ($this->lexer->optionalMatch(Token::String, $token) || $this->lexer->optionalMatch(Token::Number, $token)) {
				return $token->getValue();
			}
			
			if ($this->lexer->peek()->getType() == Token::Minus) {
				$this->lexer->match(Token::Minus);
				$token = $this->lexer->match(Token::Number);
				return 0 - $token->getValue();
			}
			
			if ($this->lexer->optionalMatch(Token::True)) {
				return true;
			}
			
			if ($this->lexer->optionalMatch(Token::False)) {
				return false;
			}
			
			return null;
		}
		
		/**
		 * Helper for parseJson
		 * @return mixed
		 * @throws LexerException
		 * @throws ParserException
		 */
		private function parseJsonValue(): mixed {
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
		 * Parse JSON string. Not perfect but will do for now
		 * @return array
		 * @throws LexerException
		 * @throws ParserException
		 */
		protected function parseJson(): array {
			$parameters = [];
			
			do {
				$parameterKey = new Token();
				
				if (!$this->lexer->optionalMatch(Token::String, $parameterKey)) {
					if (!$this->lexer->optionalMatch(Token::Number, $parameterKey)) {
						throw new ParserException("Expected number or string, got " . $parameterKey->toString($parameterKey->getType()));
					}
				}
				
				if (!$this->lexer->optionalMatch(Token::Equals)) {
					$parameters[] = $parameterKey->getValue();
					continue;
				}
				
				$value = $this->parseJsonValue();
				
				if ($value === null) {
					throw new ParserException("Invalid value type");
				}
				
				$parameters[$parameterKey->getValue()] = $value;
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			return $parameters;
		}
		
		/**
		 * Parse a string of parameters
		 * @return array
		 * @throws LexerException
		 * @throws ParserException
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
		 * Resolve a class name using the imports
		 * @param string $className Name or alias of the class
		 * @return string Fully qualified class name
		 */
		protected function resolveClassName(string $className): string {
			// Already fully qualified
			if (str_starts_with($className, '\\')) {
				return $className;
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
				
				// Handle the handle case where imported fully qualified class with segments is used with fewer segments
				// e.g., imported "A\B\C\D" but using "@B\C\D" or "@C\D"
				foreach ($this->imports as $importAlias => $importClass) {
					$importParts = explode('\\', $importClass);
					$matchParts = array_intersect($importParts, explode('\\', $className));
					
					if (count($matchParts) > 0) {
						// Check if segments match and are in the right order
						$importSegments = implode('\\', array_slice($importParts, -count($matchParts)));
						if ($importSegments === implode('\\', $matchParts)) {
							return $importClass;
						}
					}
				}
			}
			
			// Return as is - will be looked up in default namespaces
			return $className;
		}
		
		/**
		 * Parse the Docblock
		 * @return array
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(): array {
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
		}
	}