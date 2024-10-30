<?php
	
	namespace Services\Signalize;
	
	class FunctionSignatures {
		
		private $buildInFunctions;
		private $lookupTable;
		
		/**
		 * FunctionSignatures constructor.
		 * The first character is return type, other characters are parameter types.
		 */
        public function __construct() {
			$this->buildInFunctions = [
				'FloatToInt'                  => 'if',
				'IntToFloat'                  => 'fi',
				'StrToInt'                    => 'is',
				'StrToFloat'                  => 'fs',
				'IntToStr'                    => 'si',
				'FloatToStr'                  => 'sf',
				'Round'                       => 'if',
				'BoolToStr'                   => 'sb',
				'StrToBool'                   => 'bs',
				'Lowercase'                   => 'ss',
				'Uppercase'                   => 'ss',
				'Pos'                         => 'issi:0',
				'Copy'                        => 'ssii',
				'Random'                      => 'i',
				'Length'                      => 'is',
				'StrReplace'                  => 'ssss',
				'IsNumeric'                   => 'bs',
				'IsBool'                      => 'bs',
				'IsInteger'                   => 'bs',
				'Concat'                      => 'ssss:""s:""s:""s:""s:""s:""s:""s:""s:""',
				'Write'                       => 'vs',
				'GetSelectedOptionId'         => 'sss',
				'GetSelectedOptionExtraValue' => 'ssss',
			];
			
			$this->lookupTable = [
				'v' => 'void',
				'i' => 'int',
				's' => 'string',
				'f' => 'float',
				'b' => 'bool',
			];
		}
		
		/**
		 * Transform the identifier list into an array
		 * @param string $configuration
		 * @return array
		 */
		protected function parseSignature(string $configuration): array {
			$i = 0;
			$result = [];
			$len = strlen($configuration);
			
			while ($i < $len) {
				// store identifier
				$identifier = [
					'type'  => $this->lookupTable[$configuration[$i]],
					'value' => null
				];
				
				// go to next character
				++$i;
				
				// find out if there's a default value
				if (($i < $len) && ($configuration[$i] == ':')) {
					++$i;
					$default = '';
					
					// quote encapsulated
					if ($configuration[$i] == '"') {
						++$i;
						
						while ($i < $len) {
							if ($configuration[$i] == '"') {
								++$i;
								break;
							}
							
							$default .= $configuration[$i++];
						}
						
						$identifier['value'] = $default;
						$result[] = $identifier;
						continue;
					}
					
					// find numbers and dots
					while ($i < $len && (ctype_digit($configuration[$i]) || $configuration[$i] == ".")) {
						$default .= $configuration[$i++];
					}
					
					$identifier['value'] = $default;
					$result[] = $identifier;
					continue;
				}
				
				$result[] = $identifier;
			}
			
			return $result;
		}
		
		/**
		 * Returns a list of built-in functions
		 * @return int[]|string[]
		 */
		public function getList(): array {
			return array_keys($this->buildInFunctions);
		}
		
		/**
		 * Returns true if the built-in function exists, false if not
		 * @param string $keyword
		 * @return bool
		 */
		public function buildInFunctionExists(string $keyword): bool {
			return isset($this->buildInFunctions[$keyword]);
		}
		
		/**
		 * Returns the signature of the built-in function
		 * @param string $keyword
		 * @return array|null
		 */
		public function getBuiltInFunctionSignature(string $keyword): ?array {
			if (!$this->buildInFunctionExists($keyword)) {
				return null;
			}
			
			return $this->parseSignature(substr($this->buildInFunctions[$keyword], 1));
		}
		
		/**
		 * Returns the return type of the built-in function
		 * @param string $keyword
		 * @return string
		 */
		public function getBuiltInFunctionReturnType(string $keyword): ?string {
			if (!$this->buildInFunctionExists($keyword)) {
				return null;
			}
			
			return $this->lookupTable[substr($this->buildInFunctions[$keyword], 0, 1)];
		}
	}