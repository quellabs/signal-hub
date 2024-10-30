<?php
    
    namespace Services\ObjectQuel;

	use Services\ObjectQuel\Rules\Range;
	use Services\ObjectQuel\Rules\Retrieve;
	
	class Parser {
        
        protected Lexer $lexer;
        private Range $rangeRule;
		private Retrieve $retrieveRule;
		
		/**
         * Parser constructor.
         * @param Lexer $lexer
         */
        public function __construct(Lexer $lexer) {
            $this->lexer = $lexer;
            $this->rangeRule = new Range($lexer);
            $this->retrieveRule = new Retrieve($lexer);
        }
		
		/**
		 * Parse queries
		 * @return AstInterface|null
		 * @throws LexerException|ParserException
		 */
		public function parse(): ?AstInterface {
			// Blijf bereikdefinities parsen zolang het volgende token een 'Range' type is.
			$ranges = [];

			while ($this->lexer->peek()->getType() == Token::Range) {
				$ranges[] = $this->rangeRule->parse();
			}
			
			// Doorgaan met parsen totdat een break-conditie wordt bereikt.
			$queries = [];

			do {
				// Haal het volgende token op zonder de positie in de lexer te veranderen.
				$token = $this->lexer->peek();
				
				// Controleer of het token een 'Retrieve' type is.
				switch($token->getType()) {
					case Token::Retrieve :
						$queries[] = $this->retrieveRule->parse($ranges);
						break;
						
					default :
						throw new ParserException("Unexpected token '{$token->getValue()}' on line {$this->lexer->getLineNumber()}");
				}
			} while ($this->lexer->peek()->getType() !== Token::Eof);
			
			// Retourneer het eerste query AST-object uit de array.
			// Opmerking: Dit gaat ervan uit dat er maar één query is.
			return $queries[0];
		}
    }