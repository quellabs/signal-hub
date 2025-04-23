<?php
	
	namespace Quellabs\ObjectQuel\AnnotationsReader;
	
	use Quellabs\ObjectQuel\Kernel\BasicEnum;
	
	/**
	 * Class Token
	 * @package Quellabs\\ObjectQuel\AnnotationsReader
	 */
	class Token extends BasicEnum {
		const int None = 0;
		const int Eof = 1;
		const int Annotation = 2;
		const int Comma = 3;
		const int Dot = 4;
		const int ParenthesesOpen = 5;
		const int ParenthesesClose = 6;
		const int CurlyBraceOpen = 7;
		const int CurlyBraceClose = 8;
		const int Equals = 9;
		const int LargerThan = 10;
		const int SmallerThan = 11;
		const int String = 12;
		const int Number = 13;
		const int Parameter = 14;
		const int True = 15;
		const int False = 16;
		const int BracketOpen = 17;
		const int BracketClose = 18;
		const int Plus = 19;
		const int Minus = 20;
		const int Underscore = 21;
		const int Star = 22;
		const int Variable = 23;
		const int Colon = 24;
		const int Semicolon = 25;
		const int Slash = 26;
		const int Backslash = 27;
		const int Pipe = 28;
		const int Percentage = 29;
		const int Hash = 30;
		const int Ampersand = 31;
		const int Hat = 32;
		const int Copyright = 33;
		const int Pound = 34;
		const int Euro = 35;
		const int Exclamation = 36;
		const int Question = 37;
		const int Equal = 38;
		const int Unequal = 39;
		const int LargerThanOrEqualTo = 40;
		const int SmallerThanOrEqualTo = 41;
		const int LogicalAnd = 42;
		const int LogicalOr = 43;
		const int BinaryShiftLeft = 44;
		const int BinaryShiftRight = 45;
		const int Arrow = 46;
		const int Dollar = 47;
		
		protected string|int $type;
		protected mixed $value;
		
		/**
		 * Token constructor.
		 * @param int|string $type
		 * @param null $value
		 */
		public function __construct(int|string $type = Token::None, $value = null) {
			$this->type = $type;
			$this->value = $value;
		}
		
		/**
		 * Returns the Token type
		 * @return int|string
		 */
		public function getType(): int|string {
			return $this->type;
		}
		
		/**
		 * Returns the (optional) value or null if there none
		 * @return mixed
		 */
		public function getValue(): mixed {
			return $this->value;
		}
	}