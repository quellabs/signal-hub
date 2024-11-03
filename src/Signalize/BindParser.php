<?php
	
	namespace Services\Signalize;
	
	use Services\Signalize\Ast\AstBindContainer;
	use Services\Signalize\Ast\AstTokenStream;
	use Services\Signalize\Rules\BindClick;
	use Services\Signalize\Rules\BindCss;
	use Services\Signalize\Rules\BindEnabled;
	use Services\Signalize\Rules\BindOptions;
	use Services\Signalize\Rules\BindStyle;
	use Services\Signalize\Rules\BindValue;
	use Services\Signalize\Rules\BindVisible;
	use Services\Signalize\Visitors\VisitorConvertToByteCode;
	
	class BindParser extends Parser {
		
		/**
		 * BindParser constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			parent::__construct($lexer);
		}
		
		/**
		 * Parsers the input string
		 * @param array $globalVariables
		 * @return Ast\AstTokenStream
		 * @throws \Exception
		 */
		public function parse(array $globalVariables = []): AstInterface {
			$bindCss = new BindCss($this->lexer);
			$bindStyle = new BindStyle($this->lexer);
			$bindVisible = new BindVisible($this->lexer);
			$bindEnabled = new BindEnabled($this->lexer);
			$bindOptions = new BindOptions($this->lexer);
			$bindValue = new BindValue($this->lexer);
			$bindClick = new BindClick($this->lexer);
			
			$result = [];
			
			do {
				$token = $this->lexer->peek();
				$key = $token->getValue();
				
				$result[$key] = match ($key) {
					'visible' => $bindVisible->parse(),
					'enabled' => $bindEnabled->parse(),
					'css' => $bindCss->parse(),
					'style' => $bindStyle->parse(),
					'options' => $bindOptions->parse(),
					'value' => $bindValue->parse(),
					'click' => $bindClick->parse(),
					default => throw new \Exception("SyntaxError: Illegal binding {$key}"),
				};
			} while ($this->lexer->optionalMatch(Token::Comma));
			
			return new AstBindContainer($result);
		}
		
		/**
		 * Convert AST top bytecode
		 * @param AstInterface $ast
		 * @param string $separator The boundary string.
		 * @return string
		 */
		public function convertToBytecode(AstInterface $ast, string $separator="||"): string {
			$result = [];
			
			foreach($ast->getBinds() as $key => $value) {
				$byteCodeConverter = new VisitorConvertToByteCode();
				
				$value->accept($byteCodeConverter);
				
				$result[$key] = $byteCodeConverter->getBytecodes($separator);
			}
			
			return json_encode($result);
		}
		
	}