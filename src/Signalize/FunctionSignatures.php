<?php
	
	namespace Services\Signalize;
	
	class FunctionSignatures {
		
		private array $buildInFunctions;
		private array $lookupTable;
		
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
				'Round'                       => 'ff',
				'Floor'                       => 'ff',
				'Ceil'                        => 'ff',
				'Frac'                        => 'ff',
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
				'WriteLn'                     => 'vs',
				'Chr'                         => 'si',
				'Ord'                         => 'is',
				'Odd'                         => 'bi',
				'Inc'                         => 'vri',
				'Dec'                         => 'vri',
				'GetSelectedOptionId'         => 'sss',
				'GetSelectedOptionExtraValue' => 'ssss',
				'SetValue'                    => 'vsss',
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
			$result = [];
			$len = strlen($configuration);
			
			for ($i = 0; $i < $len; $i++) {
				$currentChar = $configuration[$i];
				
				// Reference
				if ($currentChar == "r") {
					$nextChar = $configuration[$i + 1];
					
					$result[] = [
						'type'          => 'reference',
						'target_type'   => $this->lookupTable[$nextChar] ?? null,
						'default_value' => false,
						'value'         => null
					];
					
					++$i;
					continue;
				}
				
				// Normal type
				$identifier = [
					'type'          => $this->lookupTable[$currentChar] ?? null,
					'target_type'   => null,
					'default_value' => false,
					'value'         => null
				];
				
				if (($i + 1 < $len) && ($configuration[$i + 1] == ':')) {
					// Skip type and ':' character
					$i = $i + 2;
					
					// Store that there's a default value
					$identifier['default_value'] = true;
					
					if ($i < $len) {
						if ($configuration[$i] == '"') {
							$identifier['value'] = $this->parseQuotedValue($configuration, $i);
						} else {
							$identifier['value'] = $this->parseNumericValue($configuration, $i);
						}
					}
				}
				
				$result[] = $identifier;
			}
			
			return $result;
		}
		
		/**
		 * Parse a quoted string value
		 * @param string $configuration
		 * @param int &$i
		 * @return string
		 */
		private function parseQuotedValue(string $configuration, int &$i): string {
			$value = '';
			$len = strlen($configuration);
			
			for (++$i; $i < $len; ++$i) {
				if ($configuration[$i] == '"') {
					break;
				}
				
				$value .= $configuration[$i];
			}
			
			return $value;
		}
		
		/**
		 * Parse a numeric value
		 * @param string $configuration
		 * @param int &$i
		 * @return string
		 */
		private function parseNumericValue(string $configuration, int &$i): string {
			$value = '';
			$len = strlen($configuration);
			
			while ($i < $len && (ctype_digit($configuration[$i]) || $configuration[$i] == '.')) {
				$value .= $configuration[$i++];
			}
			
			return $value;
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
		 * @return string|null
		 */
		public function getBuiltInFunctionReturnType(string $keyword): ?string {
			if (!$this->buildInFunctionExists($keyword)) {
				return null;
			}
			
			return $this->lookupTable[substr($this->buildInFunctions[$keyword], 0, 1)];
		}
	}