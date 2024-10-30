<?php
	
	namespace Services\Signalize;
	
	class Executer {
		
		protected FunctionSignatures $functionSignatures;
		protected array $frames;
		
		/**
		 * Executer constructor
		 * @param array $globalVariables
		 */
		public function __construct(array $globalVariables = []) {
			$this->frames = [];
			$this->functionSignatures = new FunctionSignatures();
			
			if (!empty($globalVariables)) {
				$variablesInFrame = [];
				
				// Mapping van PHP typenamen naar Signalize typenamen
				$typeMapping = [
					'boolean' => 'bool',
					'integer' => 'int',
					'double'  => 'float',
					'string'  => 'string',
				];
				
				foreach ($globalVariables as $key => $value) {
					$variableType = gettype($value);
					
					// Controleer of het type ondersteund wordt
					if (!array_key_exists($variableType, $typeMapping)) {
						throw new \Exception("Unsupported type {$variableType} for variable {$key}");
					}
					
					// Voeg de variabele toe aan de frame
					$variablesInFrame[] = [
						'name'  => $key,
						'type'  => $typeMapping[$variableType],
						'value' => $value
					];
				}
				
				$this->frames[] = [
					'types'     => [],
					'variables' => $variablesInFrame
				];
			}
		}
		
		/**
		 * Fetches the requested variable
		 * @param string $name
		 * @return mixed|null
		 */
		protected function getVariable(string $name): ?array {
			$scopeStackKeys = array_reverse(array_keys($this->frames));
			
			foreach($scopeStackKeys as $scopeStackKey) {
				foreach ($this->frames[$scopeStackKey]["variables"] as $variable) {
					if ($variable["name"] == $name) {
						return $variable;
					}
				}
			}
			
			return null;
		}
		
		/**
		 * Fetches the requested variable
		 * @param array $ast
		 * @return mixed|null
		 */
		protected function getVariableFromRecord(array $ast): ?array {
			$variableInfo = $this->getVariable($ast["name"]);
			return ['type' => $ast["recordType"], 'value' => $variableInfo["contents"][$ast["recordKey"]]];
		}
		
		/**
		 * Fetches the requested variable
		 * @param string $name
		 * @return mixed|null
		 */
		protected function getType(string $name): ?array {
			$scopeStackKeys = array_reverse(array_keys($this->frames));
			
			foreach($scopeStackKeys as $scopeStackKey) {
				foreach ($this->frames[$scopeStackKey]["types"] as $typeName => $typeContents) {
					if ($typeName == $name) {
						return $typeContents;
					}
				}
			}
			
			return null;
		}
		
		/**
		 * Update the requested variable
		 * @param string $name
		 * @param $value
		 * @return void
		 */
		protected function setVariable(string $name, $value) {
			$scopeStackKeys = array_reverse(array_keys($this->frames));
			
			foreach($scopeStackKeys as $scopeStackKey) {
				foreach ($this->frames[$scopeStackKey] as &$frame) {
					foreach ($frame as &$variable) {
						if ($variable["name"] == $name) {
							$variable["value"] = $value;
						}
					}
				}
			}
		}
		
		/**
		 * Update the requested variable
		 * @param array $ast
		 * @param $value
		 * @return void
		 */
		protected function setVariableInRecord(array $ast, $value) {
			$scopeStackKeys = array_reverse(array_keys($this->frames));
			
			foreach($scopeStackKeys as $scopeStackKey) {
				foreach ($this->frames[$scopeStackKey]["variables"] as &$variable) {
					if ($variable["name"] == $ast["name"]) {
						$variable["contents"][$ast["recordKey"]] = $value;
					}
				}
			}
		}
		
		/**
		 * Executes the AST
		 * @param array $ast
		 * @return bool|float|int|mixed|null
		 * @throws \Exception
		 */
		public function evaluateAst(array $ast): mixed {
			if (empty($ast["type"])) {
				mail("floris@shoptrader.nl", "Error AST", print_r($ast, true) . "\n\n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
				return null;
			}
			
			switch ($ast['type']) {
				case 'internalFunctionCall' :
					switch ($ast['name']) {
						case 'FloatToInt':
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);
							
							return [
								'type'    => 'value',
								'subType' => 'int',
								'value'   => intval($parameter1["value"])
							];
							
						case 'IntToFloat':
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);
							
							return [
								'type'    => 'value',
								'subType' => 'float',
								'value'   => floatval($parameter1["value"])
							];
							
						case 'Write':
                            $parameter1 = $this->evaluateAst($ast["parameters"][0]);
                            echo $parameter1["value"];

							return [
								'type'    => 'value',
								'subType' => 'void',
								'value'   => null
							];
							
						case 'Uppercase':
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => strtoupper($parameter1["value"])
							];
						
						case 'Lowercase' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => strtolower($parameter1["value"])
							];
						
						case 'Pos' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);
							$parameter2 = $this->evaluateAst($ast["parameters"][1]);
							$parameter3 = $this->evaluateAst($ast["parameters"][2]);
							$pos = strpos($parameter1["value"], $parameter2["value"], $parameter3["value"]);
							
							return [
								'type'    => 'value',
								'subType' => 'int',
								'value'   => ($pos !== false) ? $pos : -1
							];
						
						case 'Copy' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);
							$parameter2 = $this->evaluateAst($ast["parameters"][1]);
							$parameter3 = $this->evaluateAst($ast["parameters"][2]);
							
							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => substr($parameter1["value"], $parameter2["value"], $parameter3["value"])
							];
						
						case 'Round' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'int',
								'value'   => round($parameter1["value"])
							];
						
						case 'StrToInt' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);
							
							return [
								'type'    => 'value',
								'subType' => 'int',
								'value'   => intval($parameter1["value"])
							];
						
						case 'StrToFloat' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'float',
								'value'   => floatval($parameter1["value"])
							];
						
						case 'IntToStr' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => sprintf("%d", $parameter1["value"])
							];
						
						case 'FloatToStr' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => sprintf("%f", $parameter1["value"])
							];
						
						case 'BoolToStr' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => $parameter1["value"] ? "true" : "false"
							];
						
						case 'StrToBool' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);

							return [
								'type'    => 'value',
								'subType' => 'bool',
								'value'   => in_array(strtolower($parameter1["value"]), ['true', '1'])
							];
                        
                        case 'Random' :
                            $parameter1 = $this->evaluateAst($ast["parameters"][0]);
                            
                            return [
                                'type'    => 'value',
                                'subType' => 'int',
                                'value'   => rand(0, $parameter1["value"] - 1)
                            ];

                        case 'Length' :
                            $parameter1 = $this->evaluateAst($ast["parameters"][0]);
                            
                            return [
                                'type'    => 'value',
                                'subType' => 'int',
                                'value'   => strlen($parameter1["value"])
                            ];

                        case 'StrReplace' :
                            $parameter1 = $this->evaluateAst($ast["parameters"][0]);
                            $parameter2 = $this->evaluateAst($ast["parameters"][1]);
                            $parameter3 = $this->evaluateAst($ast["parameters"][2]);
                            
                            return [
                                'type'    => 'value',
                                'subType' => 'string',
                                'value'   => str_replace($parameter1["value"], $parameter2["value"], $parameter3["value"])
                            ];

                        case 'IsNumeric' :
                            $parameter1 = $this->evaluateAst($ast["parameters"][0]);
                            
                            return [
                                'type'    => 'value',
                                'subType' => 'bool',
                                'value'   => is_numeric($parameter1["value"])
                            ];
						
						case 'IsInteger' :
							$parameter1 = $this->evaluateAst($ast["parameters"][0]);
							
							return [
								'type'    => 'value',
								'subType' => 'bool',
								'value'   => preg_match('/^-?\d+$/', $parameter1["value"])()
							];

                        case 'IsBool' :
                            $parameter1 = $this->evaluateAst($ast["parameters"][0]);
                            
                            return [
                                'type'    => 'value',
                                'subType' => 'bool',
                                'value'   => in_array(strtolower($parameter1["value"]), ['true', 'false', '1', '0'])
                            ];

                        case 'Concat' :
							$concat = "";
							for ($i = 0; $i < 10; ++$i) {
								$concat .= $this->evaluateAst($ast["parameters"][$i])["value"];
							}
                            
                            return [
                                'type'    => 'value',
                                'subType' => 'string',
                                'value'   => $concat
                            ];
					}
					
					break;
				
				case 'variable' :
					$variableInfo = $this->getVariable($ast["name"]);
					return ['type' => 'value', 'subType' => $variableInfo["type"], 'value' => $variableInfo["value"]];
					
				case 'variable_in_record' :
					$variableInfo = $this->getVariableFromRecord($ast);
					return ['type' => 'value', 'subType' => $variableInfo["type"], 'value' => $variableInfo["value"]];
					
				case 'assignment' :
					$valueToAssign = $this->evaluateAst($ast["right"]);
					$typeInfo = $this->getType($ast["left"]["subType"]);
					
					if (!empty($typeInfo)) {
						$this->setVariableInRecord($ast["left"], $valueToAssign["value"]);
					} else {
						$this->setVariable($ast["left"]["name"], $valueToAssign["value"]);
					}
					
					return ['type' => 'value', 'subType' => 'void'];
					
				case 'stream' :
					$this->frames[] = [
						'variables' => $ast["variables"],
						'types'     => $ast["types"]
					];
					
					foreach($ast["items"] as $item) {
						$this->evaluateAst($item);
					}
					
					array_pop($this->frames);
					
					return ['type' => 'value', 'subType' => 'void'];
					
				case 'if' :
					$condition = $this->evaluateAst($ast["condition"]);
					
					if ($condition["value"]) {
						$this->evaluateAst($ast["branch_true"]);
					} else {
						$this->evaluateAst($ast["branch_false"]);
					}
					
					return ['type' => 'value', 'subType' => 'void'];
				
				case 'ternary' :
					$condition = $this->evaluateAst($ast["condition"]);
					
					if ($condition["type"] !== "bool") {
						throw new \Exception("TypeError: Ternary operator expects condition to be boolean.");
					}
					
					if ($condition["value"]) {
						$trueBranch = $this->evaluateAst($ast["branch_true"]);
						return ['type' => 'value', 'subType' => $trueBranch["subType"], 'value' => $trueBranch["value"]];
					} else {
						$falseBranch = $this->evaluateAst($ast["branch_false"]);
						return ['type' => 'value', 'subType' => $falseBranch["subType"], 'value' => $falseBranch["value"]];
					}
				
				case 'negate':
					$argument = $this->evaluateAst($ast["argument"]);
					return ['type' => 'value', 'subType' => $argument["subType"], 'value' => 0 - $argument["value"]];
				
				case 'operator':
					$left = $this->evaluateAst($ast['left']);
					$right = $this->evaluateAst($ast['right']);
					
					// math is only possible on integers and floats
					// + (addition) is a special case that's also possible for strings: it will concatenate
                    if (($left['subType'] != $right['subType']) && !(
                        ($left["subType"] == "float" && $right["subType"] == "int") ||
                        ($left["subType"] == "int" && $right["subType"] == "float")
                    )) {
                        throw new \Exception("TypeError: unsupported operand type(s) for '{$left["subType"]}' and '{$right["subType"]}'");
                    }
					
					// promote the type to float if one of the operands is float
					if (($left["subType"] == "float") || ($right["subType"] == "float")) {
						$subType = "float";
					} else {
						$subType = $left["subType"];
					}
					
					switch ($ast['subType']) {
						case '&&':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] && $right["value"]];
						
						case '||':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] || $right["value"]];

						case '*':
							return ['type' => 'value', 'subType' => $subType, 'value' => $left["value"] * $right["value"]];
						
						case '/':
							return ['type' => 'value', 'subType' => $subType, 'value' => $left["value"] / $right["value"]];
						
						case '+':
							if (($left["subType"] == "string") && ($right["subType"] == "string")) {
								return ['type' => 'value', 'subType' => 'string', 'value' => sprintf("%s%s", $left["value"], $right["value"])];
							} else {
								return ['type' => 'value', 'subType' => $subType, 'value' => $left["value"] + $right["value"]];
							}
						
						case '-':
							return ['type' => 'value', 'subType' => $subType, 'value' => $left["value"] - $right["value"]];
						
						case '==':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] == $right["value"]];
						
						case '!=':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] != $right["value"]];
						
						case '>=':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] >= $right["value"]];
						
						case '<=':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] <= $right["value"]];
						
						case '>':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] > $right["value"]];
						
						case '<':
							return ['type' => 'value', 'subType' => "bool", 'value' => $left["value"] < $right["value"]];
						
						default:
							return ['type' => 'value', 'subType' => $subType, 'value' => $left["value"]];
					}
				
				default:
					return $ast;
			}
		}
	}