<?php
	
	namespace Quellabs\AnnotationReader\LexerParser;
	
	use Quellabs\AnnotationReader\Exception\LexerException;
	
	/**
	 * Simple lexer to dissect doc blocks
	 * @package Quellabs\AnnotationsReader
	 */
	class Lexer {
		protected string $string;
		protected int $pos;
		protected int $length;
		protected array $tokens;
		protected array $single_tokens;
		protected array $two_char_tokens;
		protected Token $lookahead;
		
		/**
		 * Lexer constructor.
		 * @param string $string
		 * @throws LexerException
		 */
		public function __construct(string $string) {
			$this->string = $string;
			$this->pos = 0;
			$this->length = strlen($string);
			
			$this->single_tokens = [
				Token::Dot              => '.',
				Token::Comma            => ',',
				Token::Equals           => '=',
				Token::LargerThan       => '>',
				Token::SmallerThan      => '<',
				Token::ParenthesesOpen  => '(',
				Token::ParenthesesClose => ')',
				Token::CurlyBraceOpen   => '{',
				Token::CurlyBraceClose  => '}',
				Token::BracketOpen      => '[',
				Token::BracketClose     => ']',
				Token::Plus             => '+',
				Token::Minus            => '-',
				Token::Underscore       => '_',
				Token::Star             => '*',
				Token::Colon            => ':',
				Token::Semicolon        => ';',
				Token::Slash            => '/',
				Token::Backslash        => '\\',
				Token::Pipe             => '|',
				Token::Percentage       => '%',
				Token::Hash             => '#',
				Token::Ampersand        => '&',
				Token::Hat              => '^',
				Token::Copyright        => '©',
				Token::Pound            => '£',
				Token::Euro             => '€',
				Token::Exclamation      => '!',
				Token::Question         => '?',
				Token::Dollar           => '$',
			];
			
			$this->two_char_tokens = [
				Token::Equal                => '==',
				Token::Unequal              => '!=',
				Token::LargerThanOrEqualTo  => '>=',
				Token::SmallerThanOrEqualTo => '<=',
				Token::LogicalAnd           => '&&',
				Token::LogicalOr            => '||',
				Token::BinaryShiftLeft      => '<<',
				Token::BinaryShiftRight     => '>>',
				Token::Arrow                => '->',
			];
			
			$this->lookahead = $this->nextToken();
		}
		
		/**
		 * Match the next token
		 * @param int $token
		 * @return Token
		 * @throws LexerException
		 */
		public function match(int $token): Token {
			if ($this->lookahead->getType() == $token) {
				$currentToken = $this->lookahead;
				$this->lookahead = $this->nextToken();
				return $currentToken;
			}
			
			throw new LexerException("Unexpected token");
		}
		
		/**
		 * Match the next token
		 * @param int $token
		 * @param Token|null $result
		 * @return bool
		 * @throws LexerException
		 */
		public function optionalMatch(int $token, Token &$result = null): bool {
			if ($this->lookahead->getType() == $token) {
				$currentToken = $this->lookahead;
				$this->lookahead = $this->nextToken();
				
				if (!is_null($result)) {
					$result = $currentToken;
				}
				
				return true;
			}
			
			return false;
		}
		
		/**
		 * Returns the next token and advances the token counter
		 * @return Token
		 * @throws LexerException
		 */
		public function get(): Token {
			$currentToken = $this->lookahead;
			$this->lookahead = $this->nextToken();
			return $currentToken;
		}
		
		/**
		 * Returns the next token without advancing the token counter
		 * @return Token
		 */
		public function peek(): Token {
			return $this->lookahead;
		}
		
		/**
		 * Advance $this->pos to the start of the next token
		 * This method skips over whitespace, newlines, and comment markers
		 * to position the cursor at the beginning of the next meaningful token
		 */
		protected function advance(): void {
			// Flag to track if we've just seen a newline and should check for a star
			// This helps with processing doc-block style comments (/* * text * */)
			$checkStar = false;
			
			while ($this->pos < $this->length) {
				// Handle newlines specifically
				// After a newline in a doc block, we might have a star '*' that should be ignored
				if ($this->string[$this->pos] == "\n") {
					// Set flag to check for star on next character
					$checkStar = true;
					++$this->pos;
					continue;
				}
				
				// Skip whitespace characters and stars after newlines
				// Whitespace includes: space, newline, carriage return, tab
				// If checkStar is true, we also skip '*' characters that follow newlines in doc blocks
				if (in_array($this->string[$this->pos], [" ", "\n", "\r", "\t"]) ||
					($checkStar && ($this->string[$this->pos] == "*"))) {
					
					// If we find and skip a star, reset the checkStar flag
					if ($this->string[$this->pos] == "*") {
						$checkStar = false;
					}
					
					++$this->pos;
					continue;
				}
				
				// Skip doc block comment markers
				// This detects the start of a comment block '/' followed by '*'
				if ($this->string[$this->pos] == '/') {
					// Reset checkStar flag since we're handling comment start explicitly
					$checkStar = false;
					
					// Check for the "/*" pattern that begins a doc block
					// The loop allows skipping multiple consecutive stars after the slash
					for ($j = 0; $j < 2; ++$j) {
						if (($this->pos + 1 < $this->length) && $this->string[$this->pos + 1] == '*') {
							++$this->pos;
						}
					}
					
					++$this->pos;
					continue;
				}
				
				// If we reach this point, we've found a non-whitespace, non-comment character
				// This should be the start of a token, so exit the loop
				break;
			}
		}
		
		/**
		 * Fetches a number from the datastream and returns it.
		 * Parses both integer and floating point numbers from the current position.
		 * Advances the position to the character after the number.
		 * @return float|int The parsed number as an integer or float
		 * @throws LexerException When the number contains multiple decimal points
		 */
		protected function fetchNumber(): float|int {
			// Initialize an empty string to build the number
			$string = "";
			
			// Continue reading characters as long as they're digits or a decimal point
			// This collects the entire number including any decimal component
			while (($this->pos < $this->length) && (ctype_digit($this->string[$this->pos]) || $this->string[$this->pos] == '.')) {
				// Add current character to string and advance position
				$string .= $this->string[$this->pos++];
			}
			
			// Determine if the number is an integer or float based on decimal points
			// Count the number of decimal points in the string
			if (substr_count($string, ".") == 0) {
				// No decimal points means it's an integer
				return intval($string);
			}
			
			// One decimal point means it's a floating point number
			if (substr_count($string, ".") == 1) {
				return floatval($string);
			}
			
			// Multiple decimal points means the number is malformed
			// This is not a valid number in most programming languages
			throw new LexerException("Malformed floating point number");
		}
		
		/**
		 * Fetches the next token
		 * @return Token
		 * @throws LexerException
		 */
		protected function nextToken(): Token {
			// advance pos to next token
			$this->advance();
			
			// end of file
			if ($this->pos == $this->length) {
				return new Token(Token::Eof);
			}
			
			// Try each token type checker in order
			$tokenCheckers = [
				'checkTwoCharToken',
				'checkNumberToken',
				'checkSingleCharToken',
				'checkStringToken',
				'checkAnnotationToken',
				'checkIdentifierToken'
			];
			
			foreach ($tokenCheckers as $checker) {
				if ($token = $this->$checker()) {
					return $token;
				}
			}
			
			// error - unknown token
			return new Token(Token::None, $this->string[$this->pos++]);
		}

		/**
		 * Check for two-character tokens (e.g., ==, !=, >=, <=, &&, ||, etc.)
		 * @return Token|null
		 */
		protected function checkTwoCharToken(): ?Token {
			// First verify that we have at least 2 characters remaining in the input
			// to avoid out-of-bounds array access
			if ($this->pos + 1 < $this->length) {
				// Iterate through all defined two-character tokens
				foreach ($this->two_char_tokens as $tctk => $tctv) {
					// Check if the current and next characters match a two-character token
					// $tctv[0] is the first character of the token
					// $tctv[1] is the second character of the token
					if (($this->string[$this->pos] == $tctv[0]) && ($this->string[$this->pos + 1] == $tctv[1])) {
						// Move position ahead by 2 to skip both characters of the token
						$this->pos += 2;
						
						// Return a token with the appropriate type (key from two_char_tokens array)
						return new Token($tctk);
					}
				}
			}
			
			// Return null if no two-character token was found at current position
			return null;
		}
		
		/**
		 * Check for number tokens (integers or floating point)
		 * @return Token|null
		 * @throws LexerException When encountering a malformed number (e.g., multiple decimal points)
		 */
		protected function checkNumberToken(): ?Token {
			// Check if the current character is a digit (0-9)
			if (ctype_digit($this->string[$this->pos])) {
				// Delegate to the fetchNumber method to parse the complete number
				// This handles both integers and floating point numbers
				// The position will be advanced in the fetchNumber method
				return new Token(Token::Number, $this->fetchNumber());
			}
			
			// Return null if no number token was found at current position
			return null;
		}
		
		/**
		 * Check for single character tokens (punctuation and operators)
		 * @return Token|null
		 */
		protected function checkSingleCharToken(): ?Token {
			// Check if the current character matches any of the defined single character tokens
			// array_search returns the key (token type) if found, or false if not found
			if (($index = array_search($this->string[$this->pos], $this->single_tokens))) {
				// Increment position to move past the single character token
				++$this->pos;
				
				// Return a token with the appropriate type (index from single_tokens array)
				// No value is needed since the token type fully identifies the character
				return new Token($index);
			}
			
			// Return null if no single character token was found at current position
			return null;
		}
		
		/**
		 * Check for string tokens (single or double quotes)
		 * @return Token|null
		 * @throws LexerException When string is not properly terminated or contains newlines
		 */
		protected function checkStringToken(): ?Token {
			// Check if current character is either a single or double quote
			if ($this->string[$this->pos] == '"' || $this->string[$this->pos] == "'") {
				// Store the specific quote character (either ' or ") to match the string ending
				$quote = $this->string[$this->pos];
				
				// Initialize an empty string to build the string content
				$string = "";
				
				// Pre-increment position to skip the opening quote, then continue until matching closing quote
				while ($this->string[++$this->pos] !== $quote) {
					// If we reach the end of input before finding the closing quote
					if ($this->pos == $this->length) {
						// String is not properly terminated - throw exception
						throw new LexerException("Unexpected end of data");
					}
					
					// If we encounter a newline character inside the string
					if ($this->string[$this->pos] == "\n") {
						// Strings cannot contain newlines - throw exception
						throw new LexerException("Unexpected newline in string");
					}
					
					// Add current character to the string content
					$string .= $this->string[$this->pos];
				};
				
				// Increment position to move past the closing quote
				++$this->pos;
				
				// Return a String token with the extracted content (without the quotes)
				return new Token(Token::String, $string);
			}
			
			// Return null if no string token was found at current position
			return null;
		}
		
		/**
		 * Check for annotation tokens (starting with @)
		 * @return Token|null
		 */
		protected function checkAnnotationToken(): ?Token {
			// Check if current character is the @ symbol that signifies an annotation
			if ($this->string[$this->pos] == '@') {
				// Consume the @ symbol and advance position
				++$this->pos;
				
				// Initialize an empty string to build the annotation name
				$string = "";
				
				// Continue reading characters as long as they're alphanumeric or backslash
				// Backslash is included to support namespaced annotations (e.g. @Namespace\Annotation)
				while (($this->pos < $this->length) && (ctype_alnum($this->string[$this->pos]) || ($this->string[$this->pos] == '\\'))) {
					// Add current character to string and advance position
					$string .= $this->string[$this->pos++];
				}
				
				// Return an Annotation token with the extracted annotation name
				// Note: The @ symbol is not included in the token value
				return new Token(Token::Annotation, $string);
			}
			
			// Return null if no annotation token was found at current position
			return null;
		}
		
		/**
		 * Check for identifier tokens (parameters, true, false)
		 * @return Token|null
		 */
		protected function checkIdentifierToken(): ?Token {
			// Check if the current character is alphabetic (a-z, A-Z)
			if (ctype_alpha($this->string[$this->pos])) {
				// Initialize an empty string to build the identifier
				$string = "";
				
				// Continue reading characters as long as they're alphanumeric or allowed special chars
				// Allowed characters include: letters, numbers, underscore, and hyphen
				while (($this->pos < $this->length) && (ctype_alnum($this->string[$this->pos]) || in_array($this->string[$this->pos], ['_', '-']))) {
					// Add current character to string and advance position
					$string .= $this->string[$this->pos++];
				}
				
				// Special handling for boolean literals
				// Case-insensitive comparison for "true" and "false"
				if (strcasecmp($string, "true") == 0) {
					return new Token(Token::True);
				} elseif (strcasecmp($string, "false") == 0) {
					return new Token(Token::False);
				} else {
					return new Token(Token::Parameter, $string);
				}
			}
			
			// Return null if no identifier token was found at current position
			return null;
		}
	}