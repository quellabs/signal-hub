<?php
    
    namespace Services\AnnotationsReader;

    use Throwable;
    
    class Parser {
        
        protected $lexer;
        protected $ignore_annotations;
    
        /**
         * Parser constructor.
         * @param Lexer $lexer
         */
        public function __construct(Lexer $lexer) {
            $this->lexer = $lexer;
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
		 * @return array|bool|mixed|null
		 * @throws LexerException
		 * @throws ParserException
		 */
		private function parseJsonValue() {
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
                        if ((str_starts_with($value, "OA\\")) !== false) {
                            $token = $this->lexer->get();
                            continue;
                        }
                        
                        if ((str_starts_with($value, "Orm\\")) !== false) {
                            $tokenName = "\\Services\\AnnotationsReader\\Annotations\\{$value}";
                        } elseif (!str_contains($value, "\\")) {
							$tokenName = "\\Services\\AnnotationsReader\\Annotations\\{$value}";
                        } else {
							$tokenName = $value;
                        }
    
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