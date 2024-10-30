<?php
	
	namespace Services\Signalize;
	
	class BindExecuter extends Executer {
		
		private $config_item;
		private $map;
		private $configData;
		
		/**
		 * BindExecuter constructor
		 * @param array $configItem
		 * @param array $map
		 * @param array $flattenedConfigData
		 * @param array $globalVariables
		 * @throws \Exception
		 */
		public function __construct(array $configItem=[], array $map=[], array $flattenedConfigData=[], array $globalVariables=[]) {
			$this->config_item = $configItem;
			$this->map = $map;
			$this->configData = $flattenedConfigData;
			parent::__construct($globalVariables);
		}
		
		/**
		 * Array search but with a callback
		 * @param array $arr
		 * @param $func
		 * @return false|int|string
		 */
		protected function arraySearchFunc(array $arr, $func) {
			foreach ($arr as $key => $v) {
				if ($func($v)) {
					return $key;
				}
			}
			
			return false;
		}
		
		/**
		 * Returns the value of a given ConfigData item
		 * @param string $container
		 * @param string $key
		 * @return array|null
		 */
		private function getConfigItem(string $container, string $key): ?array {
			$filtered = array_filter($this->configData, function($item) use ($container, $key) {
				return ($item["container"] === $container) && ($item["key"] === $key);
			});
			
			if (empty($filtered)) {
				return null;
			}
			
			return $filtered[array_key_first($filtered)];
		}
		
		/**
		 * Returns the value of a given ConfigData item
		 * @param string $container
		 * @param string $key
		 * @return string|null
		 */
		private function getConfigValue(string $container, string $key): ?string {
			$firstItem = $this->getConfigItem($container, $key);
			
			if (!$firstItem || !isset($firstItem["value"])) {
				return null;
			}
			
			return (string)$firstItem["value"];
		}
		
		/**
		 * Evaluates/executes an ast
		 * @param array $ast
		 * @return array
		 */
		public function evaluateAst(array $ast, array $symbolTable=[]): array {
			switch ($ast['type']) {
				case "stVariableLookup" :
					$splitIndex = $this->arraySearchFunc($this->map["st"], function($e) use ($ast) { return $e["key"] == $ast["key"]; });

					return [
						'type'    => 'value',
						'subType' => 'string',
						'value'   => $this->map["st"][$splitIndex]["value"] ?? ""
					];
				
				case "bindVariableLookup" :
					return [
						'type'    => 'value',
						'subType' => 'string',
						'value'   => $this->getConfigValue($ast["container"], $ast["key"])
					];
				
				case 'internalFunctionCall' :
					switch ($ast['name']) {
						case 'GetSelectedOptionId':
							$container = $this->evaluateAst($ast["parameters"][0]);
							$key = $this->evaluateAst($ast["parameters"][1]);
							$element = $this->getConfigItem($container["value"], $key["value"]);
							$selectedOption = $element["selectedOption"] ?? [];
							
							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => (string)$selectedOption["id"] ?? ""
							];

						case 'GetSelectedOptionExtraValue':
							$container = $this->evaluateAst($ast["parameters"][0]);
							$key = $this->evaluateAst($ast["parameters"][1]);
							$index = $this->evaluateAst($ast["parameters"][2]);
							$element = $this->getConfigItem($container["value"], $key["value"]);
							$selectedOption = $element["selectedOption"];
							
							return [
								'type'    => 'value',
								'subType' => 'string',
								'value'   => (string)$selectedOption["options"][$index["value"]] ?? ""
							];
							
						default :
							return parent::evaluateAst($ast);
					}
				
				default :
					return parent::evaluateAst($ast);
			}
		}
		
		/**
		 * Evaluates all ast's in the array
		 * @return void
		 * @throws \Exception
		 */
		public function execute(array &$items): void {
			if (!empty($items)) {
				for ($i = 0; $i < count($items); ++$i) {
					switch ($items[$i]["type"]) {
						case "enabled" :
						case "visible" :
							$items[$i]["result"] = $this->evaluateAst($items[$i]["ast"]);
							
							if ($items[$i]["result"]["subType"] != "bool") {
								throw new \Exception("TypeError: {$items[$i]["type"]} bind expects condition to be boolean");
							}
							
							break;
							
						case 'options' :
							for ($j = 0; $j < count($items[$i]["items"]); ++$j) {
								$items[$i]["items"][$j]["result"] = $this->evaluateAst($items[$i]["items"][$j]["ast"]);
							}
							
							break;
					}
				}
			}
		}
	}