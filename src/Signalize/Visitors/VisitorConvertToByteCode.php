<?php
	
	namespace Services\Signalize\Visitors;
	
	use Services\Signalize\Ast\AstAnd;
	use Services\Signalize\Ast\AstBindClick;
	use Services\Signalize\Ast\AstBindCss;
	use Services\Signalize\Ast\AstBindEnabled;
	use Services\Signalize\Ast\AstBindOptions;
	use Services\Signalize\Ast\AstBindStyle;
	use Services\Signalize\Ast\AstBindValue;
	use Services\Signalize\Ast\AstBindVariable;
	use Services\Signalize\Ast\AstBindVisible;
	use Services\Signalize\Ast\AstBool;
	use Services\Signalize\Ast\AstExpression;
	use Services\Signalize\Ast\AstFactor;
	use Services\Signalize\Ast\AstFunctionCall;
	use Services\Signalize\Ast\AstIdentifier;
	use Services\Signalize\Ast\AstIf;
	use Services\Signalize\Ast\AstNegate;
	use Services\Signalize\Ast\AstNull;
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
	
	class VisitorConvertToByteCode implements AstVisitorInterface {
		private array $bytecodes;
		private array $handledNodes;
		
		/**
		 * Constructor for VisitorConvertToByteCode
		 */
		public function __construct() {
			$this->handledNodes = [];
			$this->bytecodes = [];
		}
		
		/**
		 * Processes an AstTokenStream node and generates the corresponding bytecode.
		 * Handles a stream of tokens by adding a placeholder and updating it after processing all tokens.
		 * @param AstTokenStream $node The token stream node to process.
		 */
		private function processTokenStreamNode(AstTokenStream $node): void {
			// Store the current position in the bytecodes array
			$currentPos = count($this->bytecodes);

			// Add a temporary token stream placeholder
			$this->bytecodes[] = "<tokenstream placeholder>";
			
			// Process each token in the token stream
			foreach ($node->getTokens() as $token) {
				$token->accept($this);
			}
			
			// Calculate the number of tokens processed
			$numberOfTokens = count($this->bytecodes) - $currentPos - 1;
			
			// Replace the token stream placeholder with the actual bytecode and declared variables
			$this->bytecodes[$currentPos] = "ts##" . $numberOfTokens . "##" . json_encode($node->getDeclaredVariables());
		}
		
		/**
		 * Processes an AstIf node and generates the corresponding bytecode.
		 * Handles if-else logic by adding placeholders and updating them after processing expressions and bodies.
		 * @param AstIf $node The if node to process.
		 */
		private function processIfNode(AstIf $node): void {
			// Store the current position in the bytecodes array
			$currentPos = count($this->bytecodes);
			
			// Add a temporary if placeholder
			$this->bytecodes[] = "<if placeholder>";
			
			// Process the expression of the if node
			$node->getExpression()->accept($this);
			
			// Process the body of the if node
			$node->getBody()->accept($this);
			
			// Check if there is an else part and process it
			if ($node->getElse()) {
				// Store the position at the end of the if-body in the bytecodes array
				$endOfBodyPos = count($this->bytecodes);
				
				// Add a temporary jmp placeholder
				$this->bytecodes[] = "<jmp placeholder>";
				
				// Replace the if placeholder with the actual bytecode position
				$this->bytecodes[$currentPos] = "if:" . count($this->bytecodes);
				
				// Process the else body
				$node->getElse()->accept($this);
				
				// Replace the jmp placeholder with the actual bytecode position
				$this->bytecodes[$endOfBodyPos] = "jmp:" . count($this->bytecodes);
			} else {
				// Replace the if placeholder with the actual bytecode position
				$this->bytecodes[$currentPos] = "if:" . count($this->bytecodes);
			}
		}
		
		/**
		 * Processes an AstWhile node and generates the corresponding bytecode.
		 * A while is basically a looped if. That's why the code is converted to if.
		 * @param AstWhile $node
		 * @return void
		 */
		public function processWhileNode(AstWhile $node): void {
			// Store the current position in the bytecodes array
			$currentPos = count($this->bytecodes);
			
			// Add a temporary if placeholder
			$this->bytecodes[] = "<if placeholder>";
			
			// Process the expression of the if node
			$node->getExpression()->accept($this);
			
			// Process the body of the if node
			$node->getBody()->accept($this);
			
			// Add jump to start
			$this->bytecodes[] = "jmp:{$currentPos}";
			
			// Replace the while placeholder with the actual bytecode position
			$this->bytecodes[$currentPos] = "if:" . count($this->bytecodes);
		}
		
		/**
		 * Visits a node and processes it according to its type.
		 * Throws an error if a variable is used that does not exist in the symbol table.
		 * @param AstInterface $node The AST node to be processed.
		 */
		public function visitNode(AstInterface $node): void {
			// Skip already handled nodes
			if (isset($this->handledNodes[spl_object_id($node)])) {
				return;
			}
			
			// Handle specific node types that require special processing using a switch statement
			switch (get_class($node)) {
				case AstBindClick::class:
				case AstBindEnabled::class:
				case AstBindOptions::class:
				case AstBindVisible::class:
				case AstBindValue::class:
					break;
				
				case AstBindCss::class:
					$this->bytecodes[] = json_encode(array_column($node->getValues(), "class"));
					break;
				
				case AstBindStyle::class:
					$this->bytecodes[] = json_encode(array_column($node->getValues(), "class"));
					break;
				
				case AstBool::class:
					$this->bytecodes[] = $node->getValue() ? "true" : "false";
					break;
				
				case AstExpression::class:
					$this->bytecodes[] = $node->getOperator();
					break;
				
				case AstFactor::class:
					$this->bytecodes[] = $node->getOperator();
					break;
				
				case AstNegate::class:
					$this->bytecodes[] = "negate";
					break;
				
				case AstNull::class:
					$this->bytecodes[] = "null";
					break;
				
				case AstAnd::class:
					$this->bytecodes[] = "and";
					break;
				
				case AstOr::class:
					$this->bytecodes[] = "or";
					break;
				
				case AstTerm::class:
					$this->bytecodes[] = $node->getOperator();
					break;
				
				case AstVariableAssignment::class:
					$this->bytecodes[] = "={$node->getName()}";
					break;
				
				case AstBindVariable::class:
					$this->bytecodes[] = "@{$node->getName()}";
					break;
				
				case AstFunctionCall::class:
					$parameterCount = count($node->getParameters());
					$this->bytecodes[] = "fc:{$node->getName()}:{$parameterCount}";
					break;
				
				case AstIdentifier::class:
					$this->bytecodes[] = "id:{$node->getName()}";
					break;
				
				case AstReferenceToIdentifier::class:
					$this->bytecodes[] = "r_id:{$node->getName()}";
					break;
				
				case AstNumber::class:
					$this->bytecodes[] = "n:{$node->getValue()}";
					break;
				
				case AstString::class:
					$this->bytecodes[] = "s:{$node->getValue()}";
					break;
				
				case AstIf::class:
					$this->processIfNode($node);
					break;
				
				case AstWhile::class:
					$this->processWhileNode($node);
					break;
				
				case AstTokenStream::class:
					$this->processTokenStreamNode($node);
					break;
				
				default:
					throw new \Exception("Unsupported node type: " . get_class($node));
			}
			
			// Mark the node as handled
			$this->handledNodes[spl_object_id($node)] = true;
		}
		
		/**
		 * Returns the bytecodes
		 * @param string $separator The boundary string.
		 * @return string
		 */
		public function getBytecodes(string $separator="||"): string {
			return implode($separator, $this->bytecodes);
		}
	}