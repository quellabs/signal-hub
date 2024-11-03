<?php
	
	namespace Services\Signalize;
	
	class Executer {
		
		protected int $pos;
		protected array $bytecode;
		protected float $startTime;
		protected int $timeLimit = 5; // tijd in seconden

		/**
		 * @var SymbolTable[] $symbolTableStack
		 */
		protected array $symbolTableStack;
		
		/**
		 * Executer constructor
		 * @param string $bytecode  The bytecode to execute
		 * @param string $separator The boundary string.
		 */
		public function __construct(string $bytecode, string $separator="||") {
			$this->pos = 0;
			$this->symbolTableStack = [];
			$this->bytecode = explode($separator, $bytecode);
			$this->startTime = microtime(true);
			$this->timeLimit = 5;
		}
		
		/**
		 * Gooi een exception wanneer de tijd limiet verstreken is
		 * @return void
		 * @throws \Exception
		 */
		protected function checkTimeLimit(): void {
			if (($this->timeLimit > 0) && ((microtime(true) - $this->startTime) > $this->timeLimit)) {
				throw new \Exception("Execution time limit of {$this->timeLimit} seconds exceeded.");
			}
		}
		
		/**
		 * Fetches the requested variable
		 * @param string $name
		 * @return mixed|null
		 */
		protected function getVariable(string $name): mixed {
			for ($i = count($this->symbolTableStack) - 1; $i >= 0; --$i) {
				if ($this->symbolTableStack[$i]->has($name)) {
					return $this->symbolTableStack[$i]->get($name);
				}
			}
			
			return null;
		}
		
		/**
		 * Update the requested variable
		 * @param string $name
		 * @param mixed $value
		 * @return void
		 */
		protected function setVariable(string $name, mixed $value): void {
			for ($i = count($this->symbolTableStack) - 1; $i >= 0; --$i) {
				if ($this->symbolTableStack[$i]->has($name)) {
					$this->symbolTableStack[$i]->set($name, $value);
					return;
				}
			}
		}
		
		/**
		 * Handle token streams
		 * @return void
		 * @throws \Exception
		 */
		protected function handleTokenStream(): void {
			$exploded = explode("##", $this->bytecode[$this->pos++]);
			$numberOfTokens = (int)$exploded[1];
			$lastToken = $this->pos + $numberOfTokens;
			$localVariables = json_decode($exploded[2], true);
			
			$this->symbolTableStack[] = new SymbolTable($localVariables);
			
			while ($this->pos < $lastToken) {
				$this->executeByteCode();
			}
			
			array_pop($this->symbolTableStack);
		}
		
		/**
		 * Execute function
		 * @param string $functionName
		 * @param array $parameters
		 * @return mixed
		 * @throws \Exception
		 */
		protected function handleFunctionCall(string $functionName, array $parameters): mixed {
			switch ($functionName) {
				case 'Round':
					return round($parameters[0]);
					
				case 'IntToFloat':
				case 'StrToFloat':
					return floatval($parameters[0]);
					
				case 'FloatToInt':
				case 'StrToInt':
					return intval($parameters[0]);
					
				case 'Write':
					return print($parameters[0]);
					
				case 'WriteLn':
					return print($parameters[0] . "\n");
					
				case 'Uppercase':
					return strtoupper($parameters[0]);
					
				case 'Lowercase':
					return strtolower($parameters[0]);
					
				case 'Pos':
					return strpos($parameters[0], $parameters[1], $parameters[2]);
					
				case 'Copy':
					return substr($parameters[0], $parameters[1], $parameters[2]);
					
				case 'Floor':
					return floor($parameters[0]);
					
				case 'Ceil':
					return ceil($parameters[0]);
					
				case 'Frac':
					return fmod($parameters[0], 1);
					
				case 'IntToStr':
					return sprintf("%d", $parameters[0]);
					
				case 'FloatToStr':
					return sprintf("%f", $parameters[0]);
					
				case 'BoolToStr':
					return $parameters[0] ? "true" : "false";
					
				case 'StrToBool':
					return in_array($parameters[0], ["true", "1"]);
					
				case 'Random':
					return rand(0, $parameters[0] - 1);
					
				case 'Length':
					return strlen($parameters[0]);
					
				case 'StrReplace':
					return str_replace($parameters[0], $parameters[1], $parameters[2]);
					
				case 'IsNumeric':
					return is_numeric($parameters[0]);
					
				case 'IsInteger':
					return preg_match('/^-?\d+$/', $parameters[0]);
					
				case 'IsBool':
					return in_array(strtolower($parameters[0]), ['true', 'false', '1', '0']);
					
				case 'Concat':
					return implode('', $parameters);

				case 'Chr':
					return chr($parameters[0]);

				case 'Ord':
					return ord($parameters[0]);

				case 'Odd':
					return round($parameters[0]) % 2 !== 0;

				case 'Inc':
					$variableValue = $this->getVariable($parameters[0]);
					$this->setVariable($parameters[0], $variableValue + 1);
					return null;

				case 'Dec':
					$variableValue = $this->getVariable($parameters[0]);
					$this->setVariable($parameters[0], $variableValue - 1);
					return null;

				default:
					return throw new \Exception("Unknown function: $functionName");
			}
		}

		/**
		 * Executes the bytecode
		 * @return bool|float|int|mixed|null
		 * @throws \Exception
		 */
		protected function executeByteCode(): mixed {
			// Controleer tijd aan het begin van elke bytecode uitvoering
			$this->checkTimeLimit();
			
			// Haal de huidige bytecode op
			$currentCode = $this->bytecode[$this->pos];
			
			// Arithmetic operations
			if (in_array($currentCode, ["+", "-", "*", "/", "==", "!=", ">", "<", ">=", "<=", "and", "or"])) {
				++$this->pos;
				$valueA = $this->executeByteCode();
				$valueB = $this->executeByteCode();
				
				return match ($currentCode) {
					"+" => $valueA + $valueB,
					"-" => $valueA - $valueB,
					"*" => $valueA * $valueB,
					"/" => $valueA / $valueB,
					"==" => $valueA === $valueB,
					"!=" => $valueA !== $valueB,
					">" => $valueA > $valueB,
					"<" => $valueA < $valueB,
					">=" => $valueA >= $valueB,
					"<=" => $valueA <= $valueB,
					"and" => $valueA && $valueB,
					"or" => $valueA || $valueB,
				};
			}
			
			// Boolean true
			if ($currentCode == "true") {
				++$this->pos;
				return true;
			}
			
			// Boolean false
			if ($currentCode == "false") {
				++$this->pos;
				return false;
			}
			
			// Numbers
			if (str_starts_with($currentCode, "n:")) {
				$number = substr($this->bytecode[$this->pos++], 2);
				return str_contains($number, ".") ? floatval($number) : intval($number);
			}
			
			// string literals
			if (str_starts_with($currentCode, "s:")) {
				return substr($this->bytecode[$this->pos++], 2);
			}
			
			// Variable assignment
			if (str_starts_with($currentCode, "=")) {
				$variableName = substr($this->bytecode[$this->pos++], 1);
				$this->setVariable($variableName, $this->executeByteCode());
				return null;
			}
			
			// Variable retrieval
			if (str_starts_with($currentCode, "id:")) {
				$variableName = substr($this->bytecode[$this->pos++], 3);
				return $this->getVariable($variableName);
			}
			
			// Reference to variable
			if (str_starts_with($currentCode, "r_id:")) {
				return substr($this->bytecode[$this->pos++], 5);
			}
			
			// Token stream handling
			if (str_starts_with($currentCode, "ts##")) {
				$this->handleTokenStream();
				return null;
			}
			
			// If
			if (str_starts_with($currentCode, "if:")) {
				$jmpWhenFalse = substr($this->bytecode[$this->pos++], 3);
				
				if (!$this->executeByteCode()) {
					$this->pos = (int)$jmpWhenFalse;
				}
				
				return null;
			}
			
			// Function calls
			if (str_starts_with($currentCode, "fc:")) {
				$exploded = explode(":", $this->bytecode[$this->pos++]);
				$functionName = $exploded[1];
				$numberOfParameters = $exploded[2];
				
				$parameters = [];
				for ($i = 0; $i < $numberOfParameters; ++$i) {
					$parameters[] = $this->executeByteCode();
				}
				
				return $this->handleFunctionCall($functionName, $parameters);
			}
			
			// Jump
			if (str_starts_with($currentCode, "jmp:")) {
				$jmp = substr($this->bytecode[$this->pos++], 4);
				$this->pos = (int)$jmp;
				return null;
			}
			
			// Negate
			if ($currentCode == "negate") {
				++$this->pos;
				return !$this->executeByteCode();
			}
			
			++$this->pos;
			return null;
		}
		
		/**
		 * Executes the bytecode until completion or until the timeout triggered.
		 * @return bool
		 */
		public function execute(int $timeLimit = 0): bool {
			try {
				$this->timeLimit = $timeLimit;
				$this->startTime = microtime(true);
				$this->executeByteCode();
				return true;
			} catch (\Exception $exception) {
				return false;
			}
		}
	}