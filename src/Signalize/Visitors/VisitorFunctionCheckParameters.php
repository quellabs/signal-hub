<?php
	
	namespace Services\Signalize\Visitors;
	
	use Services\Signalize\Ast\AstBool;
	use Services\Signalize\Ast\AstFunctionCall;
	use Services\Signalize\Ast\AstNull;
	use Services\Signalize\Ast\AstNumber;
	use Services\Signalize\Ast\AstString;
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	use Services\Signalize\FunctionSignatures;
	use Services\Signalize\ParserException;
	
	class VisitorFunctionCheckParameters implements AstVisitorInterface {
		private FunctionSignatures $functionSignatures;
		
		/**
		 * Constructor for VisitorFunctionExists
		 */
		public function __construct() {
			$this->functionSignatures = new FunctionSignatures();
		}
		
		/**
		 * Visits a node and processes it according to its type
		 * Throws an error if a variable is used that does not exist in the symbol table
		 * @param AstInterface $node
		 * @throws ParserException
		 */
		public function visitNode(AstInterface $node): void {
			// Behandel token stream nodes
			if ($node instanceof AstFunctionCall) {
				$parameters = $node->getParameters();
				$signature = $this->functionSignatures->getBuiltInFunctionSignature($node->getName());
				$paramCount = count($parameters);
				$signatureCount = count($signature);
				
				// Doe niets als het aantal parameters precies overeenkomt met de signature
				if ($paramCount === $signatureCount) {
					return;
				}
				
				// Gooi een error als het aantal parameters te veel is voor de signature
				if ($paramCount > $signatureCount) {
					throw new ParserException("Too many actual parameters in function call {$node->getName()}");
				}
				
				// Probeer parameters aan te vullen met default waarden indien mogelijk
				for ($i = $paramCount; $i < $signatureCount; $i++) {
					// Als de waarde geen default value heeft, gooi dan een exception
					if (!isset($signature[$i]["default_value"])) {
						throw new ParserException("Too few actual parameters in function call {$node->getName()}");
					}
					
					// Voeg de juiste type default waarde toe aan de parameters
					$defaultValue = $signature[$i]["value"];
					
					$parameters[] = match ($signature[$i]["type"]) {
						"int" => new AstNumber((int)$defaultValue),
						"float" => new AstNumber((float)$defaultValue),
						"string" => new AstString((string)$defaultValue),
						"bool" => new AstBool((bool)$defaultValue),
						default => new AstNull(),
					};
				}
				
				// Stel de nieuwe parameterlijst in
				$node->setParameters($parameters);
			}
		}
	}