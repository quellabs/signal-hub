<?php
	
	namespace Services\Signalize\Rules;
	
	use Services\Signalize\Ast\AstString;
	use Services\Signalize\Ast\AstTokenStream;
	use Services\Signalize\Ast\AstVariableAssignment;
	use Services\Signalize\Lexer;
	use Services\Signalize\LexerException;
	use Services\Signalize\ParserException;
	use Services\Signalize\Token;
	
	class TokenStream {
		
		protected Lexer $lexer;
		
		/**
		 * TokenStream constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
		}
		
		/**
		 * Parse a visible bind
		 * @param int $endToken
		 * @param array $globalVariables
		 * @return AstTokenStream The resulting AST node representing the parsed expression.
		 * @throws LexerException
		 * @throws ParserException
		 */
		public function parse(int $endToken = Token::Eof, array $globalVariables=[]): AstTokenStream {
			// Maak nieuwe parse objecten aan voor verschillende statement types
			$functionCall = new FunctionCall($this->lexer);
			$variableAssignment = new VariableAssignment($this->lexer);
			
			// Initialiseer arrays voor tokens en variabelen
			$tokens = [];
			$variables = [];
			
			// Voeg de globale variabelen toe aan de tokenstream
			if (!empty($globalVariables)) {
				foreach($globalVariables as $key => $value) {
					$variables[$key] = "string";
					$tokens[] = new AstVariableAssignment($key, new AstString($value));
				}
			}
			
			// Blijf parsen totdat het einde van de tokens bereikt is
			while ($this->lexer->lookahead() !== $endToken) {
				$lookahead = $this->lexer->lookahead();
				
				switch ($lookahead) {
					case Token::If:
						// Parse een if-statement en voeg het toe aan tokens
						$ifStatement = new IfStatement($this->lexer);
						$tokens[] = $ifStatement->parse();
						break;
					
					case Token::While:
						// Parse een while-statement en voeg het toe aan tokens
						$whileStatement = new WhileStatement($this->lexer);
						$tokens[] = $whileStatement->parse();
						break;
					
					case Token::IntType:
					case Token::StringType:
					case Token::FloatType:
					case Token::BoolType:
						// Match het type van de variabele
						$type = $this->lexer->match($lookahead);
						
						// Match de identifier van de variabele
						$identifier = $this->lexer->match(Token::Identifier);
						
						// Gooi een foutmelding op wanneer de variabele al gedefinieerd is
						if (isset($variables[$identifier->getValue()])) {
							throw new ParserException("Variable {$identifier->getValue()} is already declared");
						}
					
						// Voeg de variabele toe aan de lijst met variabelen
						$variables[$identifier->getValue()] = $type->getValue();
						
						// Als er een toewijzing is, parse deze dan
						if ($this->lexer->optionalMatch(Token::Equals)) {
							$tokens[] = $variableAssignment->parse($identifier->getValue());
						}
						
						// Match het puntkomma ';' na de variabele declaratie/toewijzing
						$this->lexer->match(Token::Semicolon);
						break;
					
					case Token::Identifier:
						// Match de identifier
						$identifier = $this->lexer->match(Token::Identifier);
						
						// Controleer of het een functie-aanroep of een variabele toewijzing is
						if ($this->lexer->lookahead() == Token::ParenthesesOpen) {
							$tokens[] = $functionCall->parse($identifier->getValue());
						} elseif ($this->lexer->lookahead() == Token::Equals) {
							$this->lexer->match(Token::Equals);
							$tokens[] = $variableAssignment->parse($identifier->getValue());
						} else {
							// Gooi een uitzondering bij een onverwacht token
							throw new ParserException("Unexpected token '{$identifier->getValue()}' on line {$this->lexer->getLineNumber()}");
						}
						
						// Match het puntkomma ';' na de functie-aanroep/toewijzing
						$this->lexer->match(Token::Semicolon);
						break;
					
					default:
						// Gooi een uitzondering bij een onverwacht token type
						throw new ParserException('Unexpected token type: ' . $lookahead);
				}
			}
			
			// Retourneer een nieuw AstTokenStream object met de tokens en variabelen
			return new AstTokenStream($tokens, $variables);
		}
	}