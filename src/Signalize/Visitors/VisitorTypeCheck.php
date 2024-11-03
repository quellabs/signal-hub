<?php
	
	namespace Services\Signalize\Visitors;
	
	use Services\Signalize\Ast\AstAnd;
	use Services\Signalize\Ast\AstBindVariable;
	use Services\Signalize\Ast\AstBool;
	use Services\Signalize\Ast\AstExpression;
	use Services\Signalize\Ast\AstFactor;
	use Services\Signalize\Ast\AstFunctionCall;
	use Services\Signalize\Ast\AstIdentifier;
	use Services\Signalize\Ast\AstIf;
	use Services\Signalize\Ast\AstNegate;
	use Services\Signalize\Ast\AstNumber;
	use Services\Signalize\Ast\AstOr;
	use Services\Signalize\Ast\AstReferenceToIdentifier;
	use Services\Signalize\Ast\AstString;
	use Services\Signalize\Ast\AstTerm;
	use Services\Signalize\Ast\AstTokenStream;
	use Services\Signalize\Ast\AstVariableAssignment;
	use Services\Signalize\Ast\AstWhile;
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	use Services\Signalize\FunctionSignatures;
	use Services\Signalize\ParserException;
	
	class VisitorTypeCheck implements AstVisitorInterface {
		private array $handledAsts;
		private array $symbolTables;
		private FunctionSignatures $functionSignatures;
		
		/**
		 * Constructor for VisitorVariableExists
		 */
		public function __construct() {
			$this->handledAsts = [];
			$this->symbolTables = [];
			$this->functionSignatures = new FunctionSignatures();
		}
		
		/**
		 * Returns the property type
		 * @param string $name
		 * @return string|null
		 */
		protected function getVariableType(string $name): ?string {
			for ($i = count($this->symbolTables) -1; $i >= 0; $i--) {
				if (isset($this->symbolTables[$i][$name])) {
					return $this->symbolTables[$i][$name];
				}
			}
			
			return null;
		}
		
		/**
		 * Infer the type from the AST
		 * @param AstInterface $ast
		 * @return string
		 */
		protected function inferType(AstInterface $ast): string {
			// Boolean value
			if ($ast instanceof AstBool) {
				return "bool";
			}
			
			// String value
			if ($ast instanceof AstString || $ast instanceof AstBindVariable) {
				return "string";
			}
			
			// Numbers can be either int or float
			if ($ast instanceof AstNumber) {
				return is_float($ast->getValue()) ? "float" : "int";
			}
			
			// Negated value
			if ($ast instanceof AstNegate) {
				return $this->inferType($ast->getNodeToNegate());
			}
			
			// Fetch the variable type from the symbol table
			if ($ast instanceof AstIdentifier) {
				return $this->getVariableType($ast->getName());
			}
			
			// Fetch the return type of the function from the function signature list
			if ($ast instanceof AstFunctionCall) {
				return $this->functionSignatures->getBuiltInFunctionReturnType($ast->getName());
			}
			
			// if And or Or is used, the result is always bool
			if ($ast instanceof AstAnd || $ast instanceof AstOr) {
				return 'bool';
			}
			
			// Relations
			if ($ast instanceof AstExpression || $ast instanceof AstTerm || $ast instanceof AstFactor) {
				$left = $this->inferType($ast->getLeft());
				$right = $this->inferType($ast->getRight());
				$operator = $ast->getOperator();
				
				if (in_array($operator, ['==', '!=', '>', '<', '<=', '>='])) {
					return 'bool';
				}
				
				if ($left === $right) {
					return $left;
				}
				
				return 'void';
			}
			
			// Return void by default
			return "void";
		}
		
		/**
		 * Handle token stream nodes by processing declared variables and tokens.
		 * @param AstTokenStream $node The token stream node to handle.
		 * @return void
		 */
		private function handleTokenStream(AstTokenStream $node): void {
			// Add declared variables to the symbol table stack
			$this->symbolTables[] = $node->getDeclaredVariables();
			
			// Process each token in the token stream
			foreach ($node->getTokens() as $token) {
				$token->accept($this);
			}
			
			// Remove the symbol table for the current token stream
			array_pop($this->symbolTables);
		}
		
		/**
		 * Handle function call nodes by checking and converting parameter types if necessary.
		 * @param AstFunctionCall $node The function call node to handle.
		 * @return void
		 * @throws ParserException
		 */
		private function handleFunctionCall(AstFunctionCall $node): void {
			// Add node to handled list
			$this->handledAsts[] = spl_object_id($node);

			// Get the function signature for the built-in function
			$functionName = $node->getName();
			$signature = $this->functionSignatures->getBuiltInFunctionSignature($functionName);
			$parameters = $node->getParameters();
			
			foreach ($parameters as $index => $parameter) {
				$parameterType = $signature[$index]["type"];
				$targetParameterType = $signature[$index]["target_type"];
				$inferredType = $this->inferType($parameter);
				
				// Reference to variable
				if ($parameterType == "reference") {
					// Check if the parameter is a variable
					if (!$parameter instanceof AstIdentifier) {
						throw new ParserException("Invalid argument for {$functionName} function. A variable identifier is required.");
					}
					
					// Check the parameter type
					if ($targetParameterType !== $inferredType) {
						throw new ParserException("Incompatible type for parameter in call to '{$functionName}'. Expected '{$targetParameterType}', but got '{$inferredType}'");
					}
					
					// Add parameter to list
					$parameters[$index] = new AstReferenceToIdentifier($parameter->getName());
					continue;
				}
				
				// Convert the parameter type if necessary
				$parameters[$index] = $this->convertParameterType($parameterType, $inferredType, $parameter, $functionName);
			}
			
			// Set the converted parameters back to the node
			$node->setParameters($parameters);
		}
		
		/**
		 * Handle variable assignment nodes by checking and converting value types if necessary.
		 * @param AstVariableAssignment $node The variable assignment node to handle.
		 * @return void
		 * @throws ParserException
		 */
		private function handleVariableAssignment(AstVariableAssignment $node): void {
			// Get the declared type of the variable
			$variableType = $this->getVariableType($node->getName());
			
			// Infer the type of the value being assigned
			$inferredType = $this->inferType($node->getValue());
			
			// If the variable type matches the inferred type, mark the node as handled and return
			if ($variableType === $inferredType) {
				$this->handledAsts[] = spl_object_id($node);
				return;
			}
			
			// Convert the value type if necessary
			$node->setValue($this->convertValueType($variableType, $inferredType, $node->getValue()));
		}
		
		/**
		 * If expression should be bool
		 * @param AstIf $node
		 * @return void
		 * @throws ParserException
		 */
		private function handleIf(AstIf $node): void {
			$inferredType = $this->inferType($node->getExpression());

			if ($inferredType !== "bool") {
				throw new ParserException("TypeError: Incompatible types: 'bool' and '{$inferredType}'");
			}
		}
		
		/**
		 * If expression should be bool
		 * @param AstWhile $node
		 * @return void
		 * @throws ParserException
		 */
		private function handleWhile(AstWhile $node): void {
			$inferredType = $this->inferType($node->getExpression());

			if ($inferredType !== "bool") {
				throw new ParserException("TypeError: Incompatible types: 'bool' and '{$inferredType}'");
			}
		}
		
		/**
		 * Convert the type of parameter if it is not of the expected type.
		 * @param string $expectedType The expected type of the parameter.
		 * @param string $actualType The actual type of the parameter.
		 * @param mixed $parameter The parameter to be converted.
		 * @param string $functionName The name of the function being called.
		 * @return AstInterface The value
		 * @throws ParserException If the parameter type cannot be converted.
		 */
		private function convertParameterType(string $expectedType, string $actualType, mixed $parameter, string $functionName): AstInterface {
			// Return the identifier if the types match
			if ($expectedType === $actualType) {
				return $parameter;
			}
			
			// Convert float to int
			if ($expectedType === "int" && $actualType === "float") {
				return new AstFunctionCall("FloatToInt", [$parameter]);
			}
			
			// Convert in to float
			if ($expectedType === "float" && $actualType === "int") {
				return new AstFunctionCall("IntToFloat", [$parameter]);
			}
			
			// Incompatible types. Throw error
			throw new ParserException("Incompatible type for parameter in call to '{$functionName}'. Expected '{$expectedType}', but got '{$actualType}'");
		}
		
		/**
		 * Convert the type of a value if it is not of the expected type.
		 * @param string $expectedType The expected type of the value.
		 * @param string $actualType The actual type of the value.
		 * @param mixed $value The value to be converted.
		 * @return AstFunctionCall The function call to convert the value type.
		 * @throws ParserException If the value type cannot be converted.
		 */
		private function convertValueType(string $expectedType, string $actualType, mixed $value): AstFunctionCall {
			if ($expectedType === "int" && $actualType === "float") {
				return new AstFunctionCall("FloatToInt", [$value]);
			} elseif ($expectedType === "float" && $actualType === "int") {
				return new AstFunctionCall("IntToFloat", [$value]);
			} else {
				throw new ParserException("TypeError: Incompatible types: '{$expectedType}' and '{$actualType}'");
			}
		}
		
		/**
		 * Visits a node and processes it according to its type
		 * Throws an error if a variable is used that does not exist in the symbol table
		 * @param AstInterface $node
		 * @throws ParserException
		 */
		public function visitNode(AstInterface $node): void {
			// Skip nodes that have already been handled
			if (in_array(spl_object_id($node), $this->handledAsts)) {
				return;
			}
			
			if ($node instanceof AstTokenStream) {
				$this->handleTokenStream($node);
			} elseif ($node instanceof AstFunctionCall) {
				$this->handleFunctionCall($node);
			} elseif ($node instanceof AstVariableAssignment) {
				$this->handleVariableAssignment($node);
			} elseif ($node instanceof AstIf) {
				$this->handleIf($node);
			} elseif ($node instanceof AstWhile) {
				$this->handleWhile($node);
			}
			
			// Add node to the list of handled nodes
			$this->handledAsts[] = spl_object_id($node);
		}
	}