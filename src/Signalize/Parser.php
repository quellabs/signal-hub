<?php
	
	namespace Services\Signalize;
	
	/**
	 * Parser
	 */
	class Parser {
		
		protected Lexer $lexer;
		protected FunctionSignatures $functionSignatures;
		protected SymbolTable $symbolTable;
		protected array $characterTypes;
		
		/**
		 * Parser constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			$this->lexer = $lexer;
			$this->functionSignatures = new FunctionSignatures();
			$this->symbolTable = new SymbolTable();
			
			$this->characterTypes = [
				'void'   => 'v',
				'string' => 's',
				'int'    => 'i',
				'float'  => 'f',
				'bool'   => 'b',
			];
		}
		
		/**
		 * Convert php variables to signalize variables
		 * @param array $variables
		 * @return void
		 * @throws \Exception
		 */
		protected function addGlobalVariablesToScope(array $variables): void {
			// Mapping van PHP typenamen naar Signalize typenamen
			$typeMapping = [
				'boolean' => 'bool',
				'integer' => 'int',
				'double'  => 'float',
				'string'  => 'string',
			];
			
			foreach ($variables as $key => $value) {
				$variableType = gettype($value);
				
				// Controleer of het type ondersteund wordt
				if (!array_key_exists($variableType, $typeMapping)) {
					throw new \Exception("Unsupported type {$variableType} for variable {$key}");
				}
				
				// Voeg de variabele toe aan de frame
				$this->symbolTable->addVariable($typeMapping[$variableType], $key);
			}
		}
		
		/**
		 * Infer the type from the AST
		 * @param array $ast
		 * @return mixed|string
		 */
		protected function inferType(array $ast) {
			if (in_array($ast["type"], ["value", 'variable', 'internalFunctionCall'])) {
				return $ast["subType"];
			} elseif ($ast["type"] == "variable_in_record") {
				return $ast["recordType"];
			} elseif ($ast["type"] == "negate") {
				return $this->inferType($ast["argument"]);
			} elseif ($ast["type"] == "operator") {
				$left = $this->inferType($ast["left"]);
				$right = $this->inferType($ast["right"]);
				
				if (in_array($ast["subType"], ['==', '!=', '>', '<', "<=", '>='])) {
					return 'bool';
				} elseif (($left == "int") && ($right == "int")) {
					return "int";
				} elseif (($left == "float") && ($right == "float")) {
					return "float";
				} elseif (($left == "bool") && ($right == "bool")) {
					return "bool";
				} elseif (($left == "string") && ($right == "string")) {
					return "string";
				} else {
					return "void";
				}
			} else {
				return "void";
			}
		}
		
		/**
		 * Parses a constant or variable and returns the value info
		 * @return array
		 * @throws \Exception
		 */
		protected function parseConstantOrVariable(): array {
			$token = $this->lexer->peek();
			
			switch ($token['type']) {
				case 'operator' :
					if (($token["value"] == "+") || ($token["value"] == "-")) {
						$this->lexer->match('operator');
						
						if (($number = $this->lexer->optionalMatch('number'))) {
							return [
								'type'        => 'value',
								'subType'     => $number["subType"],
								'value'       => ($token["value"] == "-") ? 0 - $number["value"] : $number["value"],
								'line_number' => $token["line_number"]
							];
						}

						if ($token["value"] == "-") {
							return [
								'type'        => 'negate',
								'argument'    => $this->parseExpression(),
								'line_number' => $token["line_number"]
							];
						} else {
							return $this->parseExpression();
						}
					}
					
					throw new \Exception('Unexpected token ' . $token['type'] . ' ("' . $token['value'] . '") on line ' . $token["line_number"]);
					
				case 'number' :
					$this->lexer->match($token['type']);
					
					return [
						'type'        => 'value',
						'subType'     => $token['subType'],
						'value'       => $token['value'],
						'line_number' => $token["line_number"]
					];
				
				case 'string' :
					$this->lexer->match($token['type']);
					
					return [
						'type'        => 'value',
						'subType'     => 'string',
						'value'       => $token['value'],
						'line_number' => $token["line_number"]
					];
				
				case 'bool' :
					$this->lexer->match($token['type']);
					
					return [
						'type'        => 'value',
						'subType'     => 'bool',
						'value'       => $token['value'],
						'line_number' => $token["line_number"]
					];
				
				case 'identifier' :
					if ($this->functionSignatures->buildInFunctionExists($token["value"])) {
						return $this->parseFunctionCall();
					} else {
						return $this->parseVariableFetch();
					}
				
				default :
					throw new \Exception('Unexpected token ' . $token['type'] . ' ("' . $token['value'] . '") on line ' . $token["line_number"]);
			}
		}
		
		/**
		 * Parses a factor (times and divide)
		 * @return array
		 * @throws \Exception
		 */
		protected function parseFactor(): array {
			if ($this->lexer->peek()['type'] == 'brace_open') {
				$this->lexer->match('brace_open');
				$expression = $this->parseLogicalExpression();
				$this->lexer->match('brace_close');
				return $expression;
			}
			
			$constantOrVariable = $this->parseConstantOrVariable();
			$operator = $this->lexer->peek();
			
			if ($operator['type'] == 'operator' && (
				$operator['value'] == '*' ||
				$operator['value'] == '/'
			)) {
				$this->lexer->match('operator');
				
				return [
					'type'        => 'operator',
					'subType'     => $operator['value'],
					'left'        => $constantOrVariable,
					'right'       => $this->implicitConvert($this->inferType($constantOrVariable), $this->parseFactor()),
					'line_number' => $operator["line_number"]
				];
			}
			
			return $constantOrVariable;
		}
		
		/**
		 * Parses a term (add and divide)
		 * @return array
		 * @throws \Exception
		 */
		protected function parseTerm(): array {
			$factor = $this->parseFactor();
			$operator = $this->lexer->peek();
			
			if ($operator['type'] == 'operator' && (
				$operator['value'] == '+' ||
				$operator['value'] == '-'
			)) {
				$this->lexer->match('operator');
				
				return [
					'type'        => 'operator',
					'subType'     => $operator['value'],
					'left'        => $factor,
					'right'       => $this->implicitConvert($this->inferType($factor), $this->parseTerm()),
					'line_number' => $operator["line_number"]
				];
			}
			
			return $factor;
		}
		
		/**
		 * Parses an expression
		 * @return array
		 * @throws \Exception
		 */
		protected function parseExpression(): array {
			$term = $this->parseTerm();
			$operator = $this->lexer->peek();

			// ternary operator
			if ($operator['type'] == 'question_mark') {
				$this->lexer->match('question_mark');
				$trueBranch = $this->parseExpression();
				$this->lexer->match('colon');
				$falseBranch = $this->parseExpression();
				
				return [
					'type'        => 'ternary',
					'condition'   => $term,
					'true'        => $trueBranch,
					'false'       => $falseBranch,
					'line_number' => $operator["line_number"]
				];
			}
			
			// other operators
			if ($operator['type'] == 'operator' && (
				$operator['value'] == '==' ||
				$operator['value'] == '!=' ||
				$operator['value'] == '>=' ||
				$operator['value'] == '<=' ||
				$operator['value'] == '>' ||
				$operator['value'] == '<'
			)) {
				$this->lexer->match('operator');
				
				return [
					'type'        => 'operator',
					'subType'     => $operator['value'],
					'left'        => $term,
					'right'       => $this->implicitConvert($this->inferType($term), $this->parseExpression()),
					'line_number' => $operator["line_number"]
				];
			}
			
			return $term;
		}
		
		/**
		 * Parses a logical expression
		 * @return array
		 * @throws \Exception
		 */
		protected function parseLogicalExpression(): array {
			$expression = $this->parseExpression();
			$operator = $this->lexer->peek();
			
			if ($operator['type'] == 'operator') {
				$this->lexer->match('operator');
				
				return [
					'type'        => 'operator',
					'subType'     => $operator['value'],
					'left'        => $expression,
					'right'       => $this->implicitConvert($this->inferType($expression), $this->parseLogicalExpression()),
					'line_number' => $operator["line_number"]
				];
			}
			
			return $expression;
		}
		
		/**
		 * If statement parser
		 * @return array
		 * @throws \Exception
		 */
		protected function parseIf(?array $parentStream=null): array {
			$keyword = $this->lexer->match('keyword');
			$this->lexer->match('brace_open');
			$expression = $this->parseLogicalExpression();
			$this->lexer->match('brace_close');
			$this->lexer->match('curly_brace_open');
			
			$body = $this->parseTokenStream('curly_brace_close', $parentStream);
			
			if ($this->lexer->optionalMatch('else')) {
				$this->lexer->match('curly_brace_open');
				$else = $this->parseTokenStream('curly_brace_close');
			} else {
				$else = [
					'type'      => 'stream',
					'parent'    => $parentStream,
					'items'     => [],
					'variables' => [],
					'types'     => [],
				];
			}
			
			return [
				'type'         => 'if',
				'condition'    => $expression,
				'branch_true'  => $body,
				'branch_false' => $else,
				'line_number'  => $keyword["line_number"]
			];
		}
		
		/**
		 * Parse a built-in function call
		 * @return array
		 * @throws \Exception
		 */
		protected function parseFunctionCall(): array {
			$functionCall = $this->lexer->match('identifier');
			$signature = $this->functionSignatures->getBuiltInFunctionSignature($functionCall["value"]);
			
			// parse the parameters
			$this->lexer->match('brace_open');
			$parameters = [];
			
			while (!$this->lexer->optionalMatch('brace_close')) {
				$parameters[] = $this->parseLogicalExpression();
				
				if (!$this->lexer->optionalMatch('comma')) {
					break;
				}
			}
			
			$this->lexer->match('brace_close');
			
			// fill in parameters with default values
			for ($i = count($parameters); $i < count($signature); ++$i) {
				if (is_null($signature[$i]["value"])) {
					break;
				}
				
				$parameters[] = [
					'type'    => 'value',
					'subType' => $signature[$i]["type"],
					'value'   => $signature[$i]["value"]
				];
			}
			
			// check the number of parameters
			if (count($signature) != count($parameters)) {
				throw new \Exception("ArgumentCountError: {$functionCall["value"]}() expects exactly " . count($signature) . " parameters, " . count($parameters) . " given");
			}
			
			// check the parameter validity
			for ($i = 0; $i < count($parameters); ++$i) {
				$inferredType = $this->inferType($parameters[$i]);
				
				if ($inferredType !== $signature[$i]["type"]) {
					throw new \Exception("InvalidArgumentType: Expected argument of type {$signature[$i]["type"]} but received argument of type {$inferredType} when calling {$functionCall["value"]}");
				}
			}
			
			return [
				'type'        => 'internalFunctionCall',
				'name'        => $functionCall["value"],
				'subType'     => $this->functionSignatures->getBuiltInFunctionReturnType($functionCall["value"]),
				'parameters'  => $parameters,
				'line_number' => $functionCall["line_number"]
			];
		}
		
		/**
		 * Declaration of a variable
		 * @return array
		 * @throws \Exception
		 */
		protected function parseVariableDeclaration(): array {
			$type = $this->lexer->match('identifier');
			
			if (!in_array($type["value"], $this->symbolTable->getTypeList())) {
				throw new \Exception('Error: Unknown type ' . $type['type'] . ' on line ' . $type["line_number"]);
			}
			
			// grab the variable name
			$name = $this->lexer->match('identifier');
			
			// the variable name cannot be the same as a known type
			if (in_array($name["value"], $this->symbolTable->getSimpleTypes())) {
				throw new \Exception("Error: Illegal symbol '{$name['value']}' on line {$name["line_number"]}");
			}

			// check if the variable already exists. if so, show an error
			if ($this->symbolTable->variableExistsInCurrentScope($name["value"])) {
				throw new \Exception("Error: Duplicate variable declaration '{$name['value']}' on line {$name["line_number"]}");
			}
			
			// add the variable to the scope
			$this->symbolTable->addVariable($type["value"], $name["value"]);
			
			// if the type is a record, skip assignment
			if (!in_array($type["value"], $this->symbolTable->getSimpleTypes())) {
				return [
					'type'        => 'variable_declaration',
					'name'        => $name["value"],
					'subType'     => $type["value"],
					'line_number' => $type["line_number"]
				];
			}
			
			// optionally assign a value
			if (($this->lexer->peek()["type"] == "operator") && ($this->lexer->peek()["value"] == "=")) {
				$this->lexer->match('operator');
				$value = $this->implicitConvert($type["value"], $this->parseLogicalExpression());
			} elseif (strcasecmp($type["value"], "int") == 0) {
				$value = ['type' => 'value', 'subType' => 'int', 'value' => 0];
			} elseif (strcasecmp($type["value"], "float") == 0) {
				$value = ['type' => 'value', 'subType' => 'float', 'value' => 0.0];
			} elseif (strcasecmp($type["value"], "string") == 0) {
				$value = ['type' => 'value', 'subType' => 'string', 'value' => ""];
			} elseif (strcasecmp($type["value"], "bool") == 0) {
				$value = ['type' => 'value', 'subType' => 'bool', 'value' => false];
			} else {
				$value = ['type' => 'value', 'subType' => $type["value"], 'value' => null];
			}
			
			// return the AST entry for 'variableDeclaration'
			return [
				'type'        => 'assignment',
				'left'        => [
					'type'       => 'variable',
					'name'       => $name["value"],
					'subType'    => $type["value"],
				],
				'right'       => $value,
				'line_number' => $type["line_number"]
			];
		}
		
		/**
		 * Creates a record
		 * @param array|null $parentStream
		 * @param array $stream
		 * @return void
		 * @throws \Exception
		 */
		protected function parseRecord(?array $parentStream, array &$stream) {
			$recordContents = [];
			$this->lexer->match('keyword');
			$nameOfRecord = $this->lexer->match('identifier');
			
			// check if the record already exists
			if (array_key_exists($nameOfRecord["value"], $stream["types"])) {
				throw new \Exception("Duplicate type \"" . $nameOfRecord["value"] . "\" on line " . $nameOfRecord["line_number"]);
			}
			
			// parse the contents of the record
			$this->lexer->match('curly_brace_open');
			
			do {
				// fetch type
				$type = $this->lexer->match('identifier');
				
				if (!in_array($type["value"], $this->symbolTable->getTypeList())) {
					throw new \Exception('Unknown type ' . $type['type'] . ' ("' . $type['value'] . '") on line ' . $type["line_number"]);
				}

				// fetch name of variable
				$nameOfVariable = $this->lexer->match('identifier');
				
				if (array_key_exists($nameOfVariable["value"], $recordContents)) {
					throw new \Exception("Duplicate identifier \"" . $nameOfVariable["value"] . "\" for record " . $nameOfRecord["value"] . " on line " . $nameOfVariable["line_number"]);
				}
				
				// semicolon to indicate end of declaration
				$this->lexer->match('semicolon');
				
				// store value and type in the list
				if (!in_array($type["value"], $this->symbolTable->getSimpleTypes())) {
					$recordSpec = $this->symbolTable->getType($type["value"]);
					
					foreach($recordSpec["contents"] as $name => $type) {
						$recordContents[$nameOfVariable["value"] . "." . $name] = $type;
					}
				} else {
					$recordContents[$nameOfVariable["value"]] = $type["value"];
				}
			} while (!$this->lexer->optionalMatch('curly_brace_close'));
			
			// store the record into the symbol table
			$this->symbolTable->addType($nameOfRecord["value"], $recordContents);

			// store the record into the stream data
			$stream["types"][$nameOfRecord["value"]] = $recordContents;
		}
		
		/**
		 * Implicit type conversion
		 * @param string $targetType
		 * @param array $ast
		 * @return array
		 */
		protected function implicitConvert(string $targetType, array $ast): array {
			if (($targetType == 'float') && ($this->inferType($ast) == 'int')) {
				return [
					'type'        => 'internalFunctionCall',
					'name'        => 'IntToFloat',
					'subType'     => $this->functionSignatures->getBuiltInFunctionReturnType('IntToFloat'),
					'parameters'  => [$ast],
					'line_number' => $ast["line_number"]
				];
			} elseif (($targetType == 'int') && ($this->inferType($ast) == 'float')) {
				return [
					'type'        => 'internalFunctionCall',
					'name'        => 'FloatToInt',
					'subType'     => $this->functionSignatures->getBuiltInFunctionReturnType('FloatToInt'),
					'parameters'  => [$ast],
					'line_number' => $ast["line_number"]
				];
			} else {
				return $ast;
			}
		}
		
		/**
		 * Assign a value to a variable
		 * @return array
		 * @throws \Exception
		 */
		public function parseVariableAssignment(): array {
			// fetch variable record
			$variableInfo = $this->parseVariableFetch();
			
			// assignment operator is mandatory
			$operator = $this->lexer->match('operator', '=');
			
			// return assignment node
			$targetType = $variableInfo["type"] == "variable" ? $variableInfo["type"] : $variableInfo["recordType"];
			
			return [
				'type'        => 'assignment',
				'left'        => $variableInfo,
				'right'       => $this->implicitConvert($targetType, $this->parseLogicalExpression()),
				'line_number' => $operator["line_number"]
			];
		}
		
		/**
		 * Retrieves a variable
		 * @return array
		 * @throws \Exception
		 */
		public function parseVariableFetch(): array {
			// fetch the variable name
			$token = $this->lexer->match('identifier');
			$variableName = $token["value"];
			$lineNumber = $token["line_number"];
			
			// fetch the variable information
			$variableInfo = $this->symbolTable->getVariable($variableName);

			if ($variableInfo === null) {
				throw new \Exception("Undefined variable {$variableName} on line {$lineNumber}");
			}
			
			// if the variable is a simple type (e.g., int), we're done. return the info
			$simpleTypes = $this->symbolTable->getSimpleTypes();

			if (in_array($variableInfo["type"], $simpleTypes)) {
				return ['type' => 'variable', 'name' => $variableInfo["name"], 'subType' => $variableInfo["type"]];
			}
			
			// returns the spec of the record
			$typeInfo = $this->symbolTable->getType($variableInfo["type"]);
			$variableNameInsideRecord = $this->fetchVariableNameInsideRecord($lineNumber);
			
			if (!array_key_exists($variableNameInsideRecord, $typeInfo["contents"])) {
				throw new \Exception("Error: Variable {$variableNameInsideRecord} not present in Record {$variableInfo["type"]} on line {$lineNumber}");
			}
			
			return [
				'type'       => 'variable_in_record',
				'name'       => $variableInfo["name"],
				'subType'    => $variableInfo["type"],
				'recordKey'  => $variableNameInsideRecord,
				'recordType' => $typeInfo["contents"][$variableNameInsideRecord]
			];
		}
		
		/**
		 * Fetch a variable inside a record
		 * @param int $lineNumber
		 * @return string
		 * @throws \Exception
		 */
		private function fetchVariableNameInsideRecord(int $lineNumber): string {
			$variableNameInsideRecord = [];
			$this->lexer->match('dot');
			
			do {
				$variableInRecord = $this->lexer->match('identifier');
				$variableNameInsideRecord[] = $variableInRecord["value"];
			} while ($this->lexer->optionalMatch('dot'));
			
			return implode(".", $variableNameInsideRecord);
		}

		
		/**
		 * Parse a stream of tokens, e.g. a function body
		 * @return array
		 * @throws \Exception
		 */
		protected function parseTokenStream($endDesignator = "EOF", ?array $parentStream=null): array {
			$stream = [
				'type'      => 'stream',
				'parent'    => $parentStream,
				'items'     => [],
				'variables' => [],
				'types'     => [],
			];

			$this->symbolTable->pushScope();
			
			while (!$this->lexer->optionalMatch($endDesignator)) {
				$peekToken = $this->lexer->peek();
				
				if ($peekToken["type"] == "keyword") {
					$keyword = $peekToken["value"];
					$keywordLowerCase = strtolower($keyword);

					if ($keywordLowerCase == "if") {
						$stream["items"][] = $this->parseIf($stream);
					} elseif ($keywordLowerCase == 'record') {
						$this->parseRecord($parentStream, $stream);
					} else {
						throw new \Exception('Unexpected token ' . $peekToken['type'] . ' ("' . $peekToken['value'] . '") on line ' . $peekToken["line_number"]);
					}
				} elseif ($peekToken["type"] == "identifier") {
					if (in_array($peekToken["value"], $this->symbolTable->getTypeList())) {
						$stream["items"][] = $this->parseVariableDeclaration();
						$this->lexer->match('semicolon');
					} elseif ($this->symbolTable->variableExists($peekToken["value"])) {
						$stream["items"][] = $this->parseVariableAssignment();
						$this->lexer->match('semicolon');
					} elseif (in_array($peekToken["value"], $this->functionSignatures->getList())) {
						$stream["items"][] = $this->parseFunctionCall();
						$this->lexer->match('semicolon');
					} else {
						throw new \Exception('Unexpected token ' . $peekToken['type'] . ' ("' . $peekToken['value'] . '") on line ' . $peekToken["line_number"]);
					}
				} else {
					throw new \Exception('Unexpected token ' . $peekToken['type'] . ' ("' . $peekToken['value'] . '") on line' . $peekToken["line_number"]);
				}
			}
			
			$stream["variables"] = $this->symbolTable->getVariablesInCurrentScope();
			$stream["types"] = $this->symbolTable->getTypesInCurrentScope();
			$this->symbolTable->popScope();
			return $stream;
		}
		
		/**
		 * Do a typecheck run
		 * @param array $ast
		 * @return void
		 * @throws \Exception
		 */
        protected function typeChecker(array $ast) {
            switch($ast["type"]) {
                case 'stream' :
                    foreach($ast["items"] as $item) {
                        $this->typeChecker($item);
                    }
                    
                    break;
                
                case 'if' :
                case 'ternary' :
                    $this->typeChecker($ast["condition"]);
                    $this->typeChecker($ast["branch_true"]);
                    $this->typeChecker($ast["branch_false"]);
                    break;

                case 'operator' :
                    if ($this->inferType($ast) == 'void') {
                        throw new \Exception("TypeError on line {$ast["line_number"]}");
                    }
                    
                    break;
                    
                case 'assignment' :
					$typeLeft = $this->inferType($ast["left"]);
                    $typeRight = $this->inferType($ast["right"]);
					
					if ($typeLeft != $typeRight) {
						if (
							($typeLeft != "int" || $typeRight != 'float') &&
							($typeLeft != "float" || $typeRight != 'int')
						) {
							throw new \Exception("Incompatible types: {$typeRight} is not compatible with {$typeLeft} on line {$ast["line_number"]}");
						}
                    }
                    
                    break;
            }
        }
		
		/**
		 * Parse source text
		 * @return array
		 * @throws \Exception
		 */
        public function parse(array $globalVariables=[]): array {
			$this->symbolTable->pushScope($globalVariables);
			$this->addGlobalVariablesToScope($globalVariables);
			$tokenStream = $this->parseTokenStream();
			$this->symbolTable->popScope();
            $this->typeChecker($tokenStream);
            return $tokenStream;
        }
	}