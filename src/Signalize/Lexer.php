<?php
	
	namespace Services\Signalize;
	
	class Lexer {
		private string $inputString;
		private int $currentTokenIndex;
		private int $lineNumber;
		private array $tokens;
		private array $keywords;
		private array $symbols;
		private array $operators;
		private array $operatorsTwoLetters;
		private array $escapeChars;
		
		/**
		 * BindLexer constructor.
		 */
		public function __construct(string $inputString) {
			$this->inputString = $inputString;
			$this->currentTokenIndex = 0;
			$this->lineNumber = 1;
			$this->tokens = [];
			$this->keywords = [
				'IF',
				'ELSE',
				'FUNCTION',
				'RECORD',
			];
			
			$this->symbols = [
				'{' => 'curly_brace_open',
				'}' => 'curly_brace_close',
				'(' => 'brace_open',
				')' => 'brace_close',
				'.' => 'dot',
				'@' => 'at',
				':' => 'colon',
				';' => 'semicolon',
				',' => 'comma',
				'?' => 'question_mark',
			];
			
			$this->operators = [
				'>' => 'larger_than',
				'<' => 'smaller_than',
				'+' => 'plus',
				'-' => 'minus',
				'!' => 'not',
				'=' => 'assignment',
			];
			
			$this->operatorsTwoLetters = [
				'==' => 'equal',
				'!=' => 'notEqual',
				'<=' => 'smallerOrEqual',
				'>=' => 'largerOrEqual',
				'&&' => 'logicalAnd',
				'||' => 'logicalOr',
			];
			
			$this->escapeChars = [
				'\\' => "\\", 'n' => "\n", 'r' => "\r", 't' => "\t",
				'$' => "$", '"' => "\"", '\'' => "'"
			];
			
			if (!empty($inputString)) {
				$this->tokenizeString($inputString);
			}
		}
		
		/**
		 * Tokenizes a bind string
		 * const inputString = 'visible: { @container.variable == "test" && count > 5.5 }, enabled: { test == y }';
		 * const tokens = tokenizeString(inputString);
		 * console.log(tokens);
		 * @param string $inputString
		 * @return void
		 * @throws \Exception
		 */
		private function tokenizeString(string $inputString): void {
			$index = 0;
			$length = strlen($inputString);
			
			while ($index < $length) {
				// skip whitespace
				while ($index < $length && ctype_space($inputString[$index])) {
					if ($inputString[$index] == "\n") {
						++$this->lineNumber;
					}
					
					++$index;
				}
				
				// eof?
				if ($index >= $length) {
					$this->tokens[] = ['type' => 'EOF', 'value' => null, 'line_number' => $this->lineNumber];
					continue;
				}
				
				// Check for two letter operators
				if (in_array(substr($inputString, $index, 2), array_keys($this->operatorsTwoLetters))) {
					$this->tokens[] = ['type' => 'operator', 'value' => substr($inputString, $index, 2), 'line_number' => $this->lineNumber];
					$index += 2;
					continue;
				}
				
				// Check for one letter symbols
				if (in_array($inputString[$index], array_keys($this->symbols))) {
					$this->tokens[] = ['type' => $this->symbols[$inputString[$index]], 'value' => $inputString[$index], 'line_number' => $this->lineNumber];
					$index++;
					continue;
				}
				
				// Check for one letter operators
				if (in_array($inputString[$index], array_keys($this->operators))) {
					$this->tokens[] = ['type' => 'operator', 'value' => $inputString[$index], 'line_number' => $this->lineNumber];
					$index++;
					continue;
				}
				
				// Check for numeric values
				if (is_numeric($inputString[$index])) {
					$number = '';
					
					while ($index < $length && (is_numeric($inputString[$index]) || ($inputString[$index] == '.'))) {
						$number .= $inputString[$index];
						$index++;
					}
					
					if (strpos($number, '.') !== false) {
						$this->tokens[] = ['type' => 'number', 'subType' => 'float', 'value' => (float)$number, 'line_number' => $this->lineNumber];
					} else {
						$this->tokens[] = ['type' => 'number', 'subType' => 'int', 'value' => (int)$number, 'line_number' => $this->lineNumber];
					}
					
					continue;
				}
				
				// Check for string values
				// Begin als het huidige karakter een dubbel aanhalingsteken is
				if ($inputString[$index] === '"') {
					$string = '';  // Initialiseer de string die opgebouwd zal worden
					$index++;      // Verplaats de index naar het volgende karakter
					
					// Blijf de string doorlopen totdat een afsluitend karakter wordt gevonden
					while (true) {
						// Controleer of het einde van de string bereikt is zonder afsluitend aanhalingsteken
						if ($index >= $length) {
							throw new \Exception("SyntaxError: Unterminated string constant");
						}
						
						// Huidig karakter
						$currentChar = $inputString[$index];
						
						// Volgend karakter, of null als het einde van de string bereikt is
						$nextChar = $index + 1 < $length ? $inputString[$index + 1] : null;
						
						// Als het huidige karakter een afsluitend aanhalingsteken is, eindig de lus
						if ($currentChar === '"') {
							break;
						}
						
						// Als het huidige karakter een backslash is, verwerk de escape sequence
						if ($currentChar === "\\" && $nextChar) {
							// Bepaal het karakter dat vervangen moet worden
							$escapedChar = $this->escapeChars[$nextChar] ?? $currentChar;
							
							// Verhoog de index afhankelijk van of het een escape sequence was of niet
							if ($escapedChar === null) {
								$escapedChar = "\\";  // Behandel "\\" als een enkele backslash
								$index++;
							} else {
								$index += 2;  // Ga voorbij aan de escape sequence
							}
							
							// Voeg het vervangen karakter toe aan de string
							$string .= $escapedChar;
							continue;
						}
						
						// Voeg het huidige karakter toe aan de string en verhoog de index
						$string .= $currentChar;
						$index++;
					}
					
					// Voeg de opgebouwde string toe aan de lijst met tokens
					$this->tokens[] = ['type' => 'string', 'value' => $string, 'line_number' => $this->lineNumber];
					$index++; // Verhoog de index om verder te gaan met de volgende karakters
					continue; // Ga door met de volgende iteratie van de hoofdlus
				}
				
				// everything else
				$keywordOrFunction = "";
				
				while ($index < $length && (
                    ctype_alnum($inputString[$index]) ||
                    $inputString[$index] == "_")
                ) {
					$keywordOrFunction .= $inputString[$index];
					++$index;
				}
				
				// if $keywordOrFunction is empty, some error occurred. bail it
				if ($keywordOrFunction == "") {
					throw new \Exception('SyntaxError: Unable to parse the string content');
				}
				
				// boolean true
				if (strcasecmp($keywordOrFunction, "true") == 0) {
					$this->tokens[] = ['type' => 'bool', 'value' => true, 'line_number' => $this->lineNumber];
					continue;
				}
				
				// boolean false
				if (strcasecmp($keywordOrFunction, "false") == 0) {
					$this->tokens[] = ['type' => 'bool', 'value' => false, 'line_number' => $this->lineNumber];
					continue;
				}
				
				// keywords
				foreach($this->keywords as $keyword) {
					if (strcasecmp($keyword, $keywordOrFunction) == 0) {
						$this->tokens[] = ['type' => 'keyword', 'value' => $keyword, 'line_number' => $this->lineNumber];
						continue 2;
					}
				}
				
				// identifiers
				if (in_array($keywordOrFunction, ['string', 'int', 'float', 'bool'])) {
					$this->tokens[] = ['type' => 'identifier', 'value' => strtolower($keywordOrFunction), 'line_number' => $this->lineNumber];
				} else {
					$this->tokens[] = ['type' => 'identifier', 'value' => $keywordOrFunction, 'line_number' => $this->lineNumber];
				}
			}
		}

		/**
		 * Returns the current token
		 * @return array
		 */
		public function peek(): array {
			if ($this->currentTokenIndex >= count($this->tokens)) {
				return ['type' => "EOF", 'value' => null, 'line_number' => $this->lineNumber];
			}
			
			return $this->tokens[$this->currentTokenIndex];
		}
		
		/**
		 * Returns the token after the current token
		 * @return array
		 */
		public function getLookahead(): array {
			if ($this->currentTokenIndex + 1 >= count($this->tokens)) {
				return ['type' => "EOF", 'value' => null, 'line_number' => $this->lineNumber];
			}
			
			return $this->tokens[$this->currentTokenIndex + 1];
		}
		
		/**
		 * Tries to match the given type and optional value, and return the token if it does.
		 * If it doesn't, false is returned. If it matches the token index is increased.
		 * @param string $typeToMatch
		 * @param mixed $valueToMatch
		 * @return array|false
		 */
		public function optionalMatch(string $typeToMatch, $valueToMatch = null) {
			$token = $this->peek();
			
			if ($valueToMatch !== null) {
				$match = $token['type'] == $typeToMatch && $token['value'] == $valueToMatch;
			} else {
				$match = $token['type'] == $typeToMatch;
			}
			
			if ($match) {
				++$this->currentTokenIndex;
				return $token;
			}
			
			return false;
		}
		
		/**
		 * Tries to match the given type and optional value, and throws an error
		 * if it doesn't match. If it matches the token index is increased.
		 * @param string $typeToMatch
		 * @param mixed $valueToMatch
		 * @return array
		 * @throws \Exception
		 */
		public function match(string $typeToMatch, mixed $valueToMatch = null): array {
			$token = $this->optionalMatch($typeToMatch, $valueToMatch);
			
			if ($token === false) {
				throw new \Exception('Unexpected token ' . $this->peek()['type'] . ' (' . $this->peek()['value'] . ')');
			}
			
			return $token;
		}
	}