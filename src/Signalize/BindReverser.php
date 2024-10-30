<?php
	
	namespace Services\Signalize;
	
	class BindReverser {
		
		protected $frames;
		
		/**
		 * BindReverser constructor.
		 */
		public function __construct() {
			$this->frames = [];
		}
		
		/**
		 * Reverse the Ast to plain text
		 * @param $ast
		 * @return string
		 */
		protected function reverseAst($ast): string {
			$result = "";
			
			switch ($ast['type']) {
				case 'internalFunctionCall' :
					switch ($ast['name']) {
						case 'FloatToInt':
							$result .= "FloatToInt(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'IntToFloat':
							$result .= "IntToFloat(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'Write':
							$result .= "Write(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'Uppercase':
							$result .= "Uppercase(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'Lowercase' :
							$result .= "Lowercase(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'Pos' :
							$result .= "Pos(" . $this->reverseAst($ast["parameters"][0]) . "," . $this->reverseAst($ast["parameters"][1]) . "," . $this->reverseAst($ast["parameters"][2]) . ")";
							break;
						
						case 'Copy' :
							$result .= "Copy(" . $this->reverseAst($ast["parameters"][0]) . "," . $this->reverseAst($ast["parameters"][1]) . "," . $this->reverseAst($ast["parameters"][2]) . ")";
							break;
						
						case 'Round' :
							$result .= "Round(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'StrToInt' :
							$result .= "StrToInt(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'StrToFloat' :
							$result .= "StrToFloat(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;

						case 'IntToStr' :
							$result .= "IntToStr(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'FloatToStr' :
							$result .= "FloatToStr(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'BoolToStr' :
							$result .= "BoolToStr(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'StrToBool' :
							$result .= "StrToBool(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'Random' :
							$result .= "Random(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'Length' :
							$result .= "Length(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'StrReplace' :
							$result .= "StrReplace(" . $this->reverseAst($ast["parameters"][0]) . "," . $this->reverseAst($ast["parameters"][1]) . "," . $this->reverseAst($ast["parameters"][2]) . ")";
							break;
						
						case 'IsNumeric' :
							$result .= "IsNumeric(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'IsInteger' :
							$result .= "IsInteger(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'IsBool' :
							$result .= "IsBool(" . $this->reverseAst($ast["parameters"][0]) . ")";
							break;
						
						case 'Concat' :
							// gather all strings to concat
							$reversed = [];
							for ($i = 0; $i < 10; ++$i) {
								$reversed[] = $this->reverseAst($ast["parameters"][$i]);
							}

							// remove all empty elements
							while ((count($reversed) > 2) && ($reversed[array_key_last($reversed)] == "\"\"")) {
								array_pop($reversed);
							}
							
							$result .= "Concat(" . implode(",", $reversed) . ")";
							break;
					}
					
					break;
				
				case 'stream' :
					$this->frames[] = $ast["variables"];
					
					$subResult = "";
					foreach($ast["items"] as $item) {
						$subResult .= $this->reverseAst($item);
					}
					
					array_pop($this->frames);
					$result .= $subResult;
					break;
				
				case 'if' :
					$condition = $this->reverseAst($ast["condition"]);
					$trueBranch = $this->reverseAst($ast["branch_true"]);
					$falseBranch = $this->reverseAst($ast["branch_false"]);
					
					if (!empty($falseBranch)) {
						$result .= "if ({$condition}) { {$trueBranch} } else { {$falseBranch} }";
					} else {
						$result .= "if ({$condition}) { {$trueBranch} }";
					}
					
					break;
				
				case 'ternary' :
					$condition = $this->reverseAst($ast["condition"]);
					$trueBranch = $this->reverseAst($ast["branch_true"]);
					$falseBranch = $this->reverseAst($ast["branch_false"]);
					
					$result .= "{$condition} ? {$trueBranch} : {$falseBranch}";
					break;
				
				case 'negate':
					$result .= "-" . $this->reverseAst($ast["argument"]);
					break;

				case 'operator' :
					$left = $this->reverseAst($ast['left']);
					$right = $this->reverseAst($ast['right']);
					
					$result .= "{$left} {$ast['subType']} {$right}";
					break;
					
				case 'value' :
					if ($ast["subType"] == "bool") {
						$result .= $ast["value"] ? "true" : "false";
					} elseif ($ast["subType"] == "string") {
						$result .= "\"{$ast["value"]}\"";
					} elseif ($ast["subType"] == "int") {
						$result .= $ast["value"];
					} elseif ($ast["subType"] == "float") {
						$result .= $ast["value"];
					}
					
					break;

				case 'bindVariableLookup' :
					$result .= "@{$ast["container"]}.{$ast["key"]}";
					break;

				case 'stVariableLookup' :
					$result .= "@{$ast["key"]}";
					break;

				default :
					$tmp = "";
					break;
			}

			return $result;
		}
		
		/**
		 * Reverse visible condition back to text
		 * @param array $ast
		 * @return string
		 */
		protected function reverseVisible(array $ast): string {
			$result = $this->reverseAst($ast);
			return "visible: { {$result} }";
		}
		
		/**
		 * Reverse enable condition back to text
		 * @param array $ast
		 * @return string
		 */
		protected function reverseEnable(array $ast): string {
			$result = $this->reverseAst($ast);
			return "enable: { {$result} }";
		}
		
		/**
		 * Reverse options conditions back to text
		 * @param array $ast
		 * @return string
		 */
		protected function reverseOptions(array $ast): string {
			$result = [];
			
			foreach($ast["items"] as $item) {
				$result[] = "\"{$item["name"]}\": " . $this->reverseAst($item["ast"]);
			}
			
			return "options: { " . implode(", ", $result) . " }";
		}
		
		/**
		 * Reverse css conditions back to text
		 * @param array $cssItems
		 * @return string
		 */
		protected function reverseCss(array $cssItems): string {
			$result = [];
			
			foreach($cssItems as $item) {
				$result[] = "\"{$item["class"]}\": " . $this->reverseAst($item["ast"]);
			}
			
			return "css: { " . implode(", ", $result) . " }";
		}
		
		/**
		 * Reverse style conditions back to text
		 * @param array $cssItems
		 * @return string
		 */
		protected function reverseStyle(array $cssItems): string {
			$result = [];
			
			foreach($cssItems as $item) {
				$result[] = "\"{$item["class"]}\": " . $this->reverseAst($item["ast"]);
			}
			
			return "style: { " . implode(", ", $result) . " }";
		}
		
		/**
		 * Revert AST back to text
		 * @param array $items
		 * @return string
		 */
		public function reverse(array $items): string {
			$result = [];
			
			for ($i = 0; $i < count($items); ++$i) {
				switch($items[$i]["type"]) {
					case 'visible' :
						$result[] = $this->reverseVisible($items[$i]["ast"]);
						break;
					
					case 'enable' :
						$result[] = $this->reverseEnable($items[$i]["ast"]);
						break;

					case 'options' :
						$result[] = $this->reverseOptions($items[$i]);
						break;
				}
			}

			// css items
			$cssItems = array_filter($items, function($e) { return $e["type"] == "css"; });
			
			if (!empty($cssItems)) {
				$result[] = $this->reverseCss($cssItems);
			}
			
			// style items
			$styleItems = array_filter($items, function($e) { return $e["type"] == "style"; });
			
			if (!empty($styleItems)) {
				$result[] = $this->reverseStyle($styleItems);
			}
			
			return implode(", ", $result);
		}
	}