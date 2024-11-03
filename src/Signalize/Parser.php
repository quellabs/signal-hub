<?php
	
	namespace Services\Signalize;
	
	use Services\Signalize\Ast\AstTokenStream;
	use Services\Signalize\Rules\TokenStream;
	use Services\Signalize\Visitors\VisitorAstFinder;
	use Services\Signalize\Visitors\VisitorConvertToByteCode;
	use Services\Signalize\Visitors\VisitorFunctionCheckParameters;
	use Services\Signalize\Visitors\VisitorFunctionExists;
	use Services\Signalize\Visitors\VisitorTypeCheck;
	use Services\Signalize\Visitors\VisitorVariableExists;
	
	/**
	 * Parser
	 * @property VisitorVariableExists $visitorVariableExists
	 */
	class Parser {
		
		protected Lexer $lexer;
		protected TokenStream $tokenStream;
		protected VisitorVariableExists $visitorVariableExists;
		protected VisitorFunctionExists $visitorFunctionExists;
		protected VisitorFunctionCheckParameters $visitorFunctionCheckParameters;
		protected VisitorTypeCheck $visitorTypeCheck;
		
		/**
		 * Parser constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
			$this->tokenStream = new TokenStream($lexer);
			$this->visitorVariableExists = new VisitorVariableExists();
			$this->visitorFunctionExists = new VisitorFunctionExists();
			$this->visitorFunctionCheckParameters = new VisitorFunctionCheckParameters();
			$this->visitorTypeCheck = new VisitorTypeCheck();
		}
		
		/**
		 * Parse source text
		 * @return AstInterface
		 * @throws LexerException
		 * @throws ParserException
		 */
        public function parse(array $globalVariables=[]): AstInterface {
			$ast = $this->tokenStream->parse(Token::Eof, $globalVariables);
			
			$ast->accept($this->visitorVariableExists); // Controleert of variabelen gedefinieerd zijn in scope
			$ast->accept($this->visitorFunctionExists); // Controleert of functies gedefinieerd zijn in scope
			$ast->accept($this->visitorFunctionCheckParameters); // Controleert functie parameter aantallen
			$ast->accept($this->visitorTypeCheck); // Controleert op correcte types
			
			return $ast;
        }
		
		/**
		 * Returns the desired ast type in the ast tree
		 * @param AstInterface $ast
		 * @param string $astType
		 * @return AstInterface|null
		 */
		public function findAst(AstInterface $ast, string $astType): ?AstInterface {
			try {
				$astFinder = new VisitorAstFinder($astType);
				$ast->accept($astFinder);
			} catch (AstFinderException $exception) {
				return $exception->getData();
			}
			
			return null;
		}
		
		/**
		 * Convert AST top bytecode
		 * @param AstInterface $ast
		 * @param string $separator The boundary string.
		 * @return string
		 */
		public function convertToBytecode(AstInterface $ast, string $separator="||"): string {
			$byteCodeConverter = new VisitorConvertToByteCode();
			$ast->accept($byteCodeConverter);
			return $byteCodeConverter->getBytecodes($separator);
		}
	}