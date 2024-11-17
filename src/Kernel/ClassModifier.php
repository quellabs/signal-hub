<?php
	
	namespace Services\Kernel;
	
	class ClassModifier {
		private string $className;
		private string $originalCode;
		private string $filePath;
		private string $modifiedClass;
		private \ReflectionClass $reflection;
		
		/** @var array<string> Array of method names that have been added */
		private array $addedMethods;
		private array $addedProperties;
		
		/**
		 * ClassModifier constructor
		 * @param string $className
		 * @throws \ReflectionException
		 */
		public function __construct(string $className) {
			$this->reflection = new \ReflectionClass($className);
			$this->className = $className;
			$this->filePath = $this->reflection->getFileName();
			$this->originalCode = file_get_contents($this->filePath);
			$this->addedMethods = [];
			$this->addedProperties = [];
		}
		
		/**
		 * Finds the position of the class keyword
		 * @param array $tokens
		 * @return int
		 * @throws \Exception
		 */
		private function findClassStart(array $tokens): int {
			foreach ($tokens as $i => $token) {
				if (is_array($token) && $token[0] === T_CLASS) {
					return $i;
				}
			}
			
			throw new \Exception("Class niet gevonden.");
		}
		
		/**
		 * Find the position of the class's closing curly brace
		 * This method tracks nested braces to find the correct closing position
		 * of the class definition. It counts opening and closing braces to maintain
		 * the correct nesting level.
		 * @param array $tokens Array of PHP tokens to search through
		 * @param int $start Starting position in the tokens array
		 * @return int Position of the class's closing brace
		 * @throws \Exception If the class closing brace cannot be found
		 */
		private function findClassEnd(array $tokens, int $start): int {
			// Track the nesting level of curly braces
			$braceLevel = 0;
			
			// Iterate through tokens starting from the class definition
			foreach (array_slice($tokens, $start) as $position => $token) {
				// Track opening braces
				if ($token === '{') {
					$braceLevel++;
					continue;
				}
				
				// Track closing braces
				if ($token === '}') {
					$braceLevel--;
					
					// When we reach level 0, we've found the class closing brace
					if ($braceLevel === 0) {
						return $start + $position;
					}
				}
			}
			
			throw new \Exception('Unable to find the closing brace of the class definition.');
		}
		
		/**
		 * Find the optimal position to insert new property declarations
		 *
		 * This method scans through class tokens to find the last property declaration,
		 * taking into account visibility modifiers, type hints, and nested structures.
		 * If no properties exist, it returns the position after the class opening brace.
		 *
		 * @param array $tokens Array of PHP tokens to analyze
		 * @param int $classStart Starting position of the class declaration
		 * @return int Position where new properties should be inserted
		 */
		private function findLastPropertyPosition(array $tokens, int $classStart): int {
			$braceLevel = 0;
			$lastPropertyPosition = null;
			$inPropertyDeclaration = false;
			$classOpenBracePosition = null;
			
			// Iterate through tokens starting from class declaration
			foreach (array_slice($tokens, $classStart) as $offset => $token) {
				$currentPosition = $classStart + $offset;
				
				// Track brace nesting level
				if ($token === '{') {
					$braceLevel++;
					if ($braceLevel === 1) {
						$classOpenBracePosition = $currentPosition;
					}
				} elseif ($token === '}') {
					$braceLevel--;
				}
				
				// Only process tokens at the class level (not in nested structures)
				if ($braceLevel !== 1) {
					continue;
				}
				
				// Handle array tokens (most PHP syntax elements)
				if (is_array($token)) {
					// Skip documentation and comments
					if ($token[0] === T_DOC_COMMENT || $token[0] === T_COMMENT) {
						continue;
					}
					
					// Check for property visibility modifiers
					if (in_array($token[0], [T_PRIVATE, T_PROTECTED, T_PUBLIC, T_VAR])) {
						$inPropertyDeclaration = true;
						continue;
					}
					
					// Skip static keyword
					if ($token[0] === T_STATIC) {
						continue;
					}
					
					// Handle type hints within property declarations
					if (in_array($token[0], [T_STRING, T_ARRAY, T_CALLABLE]) && $inPropertyDeclaration) {
						continue;
					}
					
					// Stop searching when we reach method declarations
					if ($token[0] === T_FUNCTION) {
						break;
					}
				}
				
				// Mark the end of a property declaration
				if ($inPropertyDeclaration && $token === ';') {
					$lastPropertyPosition = $currentPosition + 1;
					$inPropertyDeclaration = false;
				}
			}
			
			// Return position after last property or after class opening brace if no properties exist
			return $lastPropertyPosition ?? ($classOpenBracePosition + 1);
		}
		
		/**
		 * Convert an array of tokens to a string
		 * @param array $tokens
		 * @return string
		 */
		private function tokensToString(array $tokens): string {
			return implode('', array_map(function ($token) {
				return is_array($token) ? $token[1] : $token;
			}, $tokens));
		}
		
		/**
		 * Returns the name of the class we're currently processing
		 * @return string
		 */
		public function getClassName(): string {
			return $this->className;
		}
		
		/**
		 * Returns true if the class has the property, false if not.
		 * @param string $propertyName
		 * @return bool
		 */
		public function hasProperty(string $propertyName): bool {
			return $this->reflection->hasProperty($propertyName) || in_array($propertyName, $this->addedProperties);
		}
		
		/**
		 * Returns true if the class has the method, false if not.
		 * @param string $methodName
		 * @return bool
		 */
		public function hasMethod(string $methodName): bool {
			return $this->reflection->hasMethod($methodName) || in_array($methodName, $this->addedMethods);
		}
		
		/**
		 * Add a new property to the class with specified visibility, type, and default value
		 * @param string $propertyName The name of the property to add
		 * @param string $visibility Visibility modifier of the property (private, protected, public)
		 * @param mixed $defaultValue Optional default value for the property
		 * @param string|null $type Optional type declaration for the property
		 * @param string|null $docComment Optional DocBlock comment for the property
		 * @throws \Exception If the property already exists in the class
		 */
		public function addProperty(string $propertyName, string $visibility = 'private', mixed $defaultValue = null, ?string $type = null, ?string $docComment = null): void {
			// Check if property already exists
			if ($this->hasProperty($propertyName)) {
				throw new \Exception("Property '$propertyName' already exists in the class.");
			}
			
			// Use modifiedClass if it exists, otherwise use originalCode
			$currentCode = $this->modifiedClass ?? $this->originalCode;
			
			// Parse the current code into tokens
			$tokens = token_get_all($currentCode);
			$classStart = $this->findClassStart($tokens);
			
			// Find the position after the last property declaration
			$insertPosition = $this->findLastPropertyPosition($tokens, $classStart);
			
			// Start building the property declaration
			$propertyCode = "\n";
			
			// Add DocBlock comment if provided
			if ($docComment !== null) {
				// Ensure the comment has proper DocBlock format
				if (!str_starts_with(trim($docComment), '/**')) {
					$docComment = "/**\n     * " . trim($docComment) . "\n     */";
				}
				
				$propertyCode .= "    " . $docComment . "\n";
			}
			
			// Build property declaration with visibility
			$propertyCode .= "    " . $visibility . " ";
			
			// Add type declaration if provided
			if ($type !== null) {
				$propertyCode .= $type . " ";
			}
			
			// Add property name
			$propertyCode .= "\$" . $propertyName;
			
			// Add default value if provided
			if ($defaultValue !== null) {
				$propertyCode .= " = " . var_export($defaultValue, true);
			}
			
			$propertyCode .= ";\n";
			
			// Insert the new property into the class code
			$beforeProperty = array_slice($tokens, 0, $insertPosition);
			$afterProperty = array_slice($tokens, $insertPosition);
			
			// Combine the code parts with the new property
			$this->modifiedClass = $this->tokensToString($beforeProperty) .
				$propertyCode .
				$this->tokensToString($afterProperty);
			
			// Track the added property
			$this->addedProperties[] = $propertyName;
		}
		
		/**
		 * Add a new method to the class
		 * @param string $methodName Name of the method to add
		 * @param string $methodCode Complete method code including signature and body
		 * @throws \Exception If the method already exists or if the method code is invalid
		 */
		public function addMethod(string $methodName, string $methodCode): void {
			// Check if method already exists in original class or has been added
			if ($this->hasMethod($methodName)) {
				throw new \Exception("Method '{$methodName}' already exists in the class.");
			}
			
			// Use modified class code if it exists, otherwise use original code
			$currentCode = $this->modifiedClass ?? $this->originalCode;
			
			// Validate that the provided method code contains the specified method name
			$methodTokens = token_get_all("<?php\n" . $methodCode);
			$foundMethodName = false;
			
			// Search for the method name in the tokens
			foreach ($methodTokens as $token) {
				if (is_array($token) &&
					$token[0] === T_STRING &&
					$token[1] === $methodName
				) {
					$foundMethodName = true;
					break;
				}
			}
			
			// Throw exception if method name not found in the provided code
			if (!$foundMethodName) {
				throw new \Exception(
					"The provided method code does not contain the method '$methodName'."
				);
			}
			
			// Parse the current class code
			$tokens = token_get_all($currentCode);
			
			// Find the class boundaries
			$classStart = $this->findClassStart($tokens);
			$classEnd = $this->findClassEnd($tokens, $classStart);
			
			// Prepare to insert the new method before the class closing brace
			$beforeClass = array_slice($tokens, 0, $classEnd);
			$afterClass = array_slice($tokens, $classEnd);
			
			// Remove the opening PHP tag from method tokens
			array_shift($methodTokens);
			
			// Combine the code parts with the new method
			$this->modifiedClass = $this->tokensToString($beforeClass) .
				"\n    " . $this->tokensToString($methodTokens) . "\n" .
				$this->tokensToString($afterClass);
			
			// Track the added method
			$this->addedMethods[] = $methodName;
		}
		
		/**
		 * Save the modified class code to a file
		 * @param string|null $outputPath Optional path to save the modified class to a different file.
		 *                                If null, overwrites the original file.
		 * @throws \RuntimeException If unable to write to the specified file
		 */
		public function save(?string $outputPath = null): void {
			// Skip if no modifications have been made
			if (empty($this->modifiedClass)) {
				return;
			}
			
			// Determine the target file path
			$targetPath = $outputPath ?? $this->filePath;
			
			// Attempt to save the modified class
			if (file_put_contents($targetPath, $this->modifiedClass) === false) {
				throw new \RuntimeException(
					"Failed to write modified class to file: {$targetPath}"
				);
			}
		}
	}