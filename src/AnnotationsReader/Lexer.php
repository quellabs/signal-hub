<?php
    
    namespace Quellabs\ObjectQuel\AnnotationsReader;

    use Quellabs\ObjectQuel\Kernel\BasicEnum;

    /**
     * Class Token
     * @package Quellabs\\ObjectQuel\AnnotationsReader
     */
    class Token extends BasicEnum {
        const None = 0;
        const Eof = 1;
        const Annotation = 2;
        const Comma = 3;
        const Dot = 4;
        const ParenthesesOpen = 5;
        const ParenthesesClose = 6;
        const CurlyBraceOpen = 7;
        const CurlyBraceClose = 8;
        const Equals = 9;
        const LargerThan = 10;
        const SmallerThan = 11;
        const String = 12;
        const Number = 13;
        const Parameter = 14;
        const True = 15;
        const False = 16;
        const BracketOpen = 17;
        const BracketClose = 18;
        const Plus = 19;
        const Minus = 20;
        const Underscore = 21;
        const Star = 22;
        const Variable = 23;
        const Colon = 24;
        const Semicolon = 25;
        const Slash = 26;
        const Backslash = 27;
        const Pipe = 28;
        const Percentage = 29;
        const Hash = 30;
        const Ampersand = 31;
        const Hat = 32;
        const Copyright = 33;
        const Pound = 34;
        const Euro = 35;
        const Exclamation = 36;
        const Question = 37;
        const Equal = 38;
        const Unequal = 39;
        const LargerThanOrEqualTo = 40;
        const SmallerThanOrEqualTo = 41;
        const LogicalAnd = 42;
        const LogicalOr = 43;
        const BinaryShiftLeft = 44;
        const BinaryShiftRight = 45;
        const Arrow = 46;
        
        protected string|int $type;
        protected mixed $value;
    
        /**
         * Token constructor.
         * @param int|string $type
         * @param null $value
         */
        public function __construct(int|string $type=Token::None, $value=null) {
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
    
    /**
     * Simple lexer to dissect doc blocks
     * @package Quellabs\\ObjectQuel\AnnotationsReader
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
         * Advance $this->pos to the start of the next token
         */
        protected function advance() {
            $checkStar = false;
            
            while ($this->pos < $this->length) {
                // skip newlines
                if ($this->string[$this->pos] == "\n") {
                    $checkStar = true;
                    ++$this->pos;
                    continue;
                }
                
                // skip empty stuff
                if (in_array($this->string[$this->pos], [" ", "\n", "\r", "\t"]) || ($checkStar && ($this->string[$this->pos] == "*"))) {
                    if ($this->string[$this->pos] == "*") {
                        $checkStar = false;
                    }
                    
                    ++$this->pos;
                    continue;
                }
                
                // skip start of comment block
                if ($this->string[$this->pos] == '/') {
                    $checkStar = false;
                    
                    for ($j = 0; $j < 2; ++$j) {
                        if (($this->pos + 1 < $this->length) && $this->string[$this->pos + 1] == '*') {
                            ++$this->pos;
                        }
                    }

                    ++$this->pos;
                    continue;
                }
                
                break;
            }
        }
    
        /**
         * Fetches a number from the datastream and returns it.
         * Throws an exception when the number is malformed.
         */
        protected function fetchNumber() {
            $string = "";
    
            while (($this->pos < $this->length) && (ctype_digit($this->string[$this->pos]) || $this->string[$this->pos] == '.')) {
                $string .= $this->string[$this->pos++];
            }
            
            if (substr_count($string, ".") == 0) {
                return intval($string);
            } elseif (substr_count($string, ".") == 1) {
                return floatval($string);
            } else {
                throw new LexerException("Malformed floating point number");
            }
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
            
            // two character tokens
            if ($this->pos + 1 < $this->length) {
                foreach($this->two_char_tokens as $tctk => $tctv) {
                    if (($this->string[$this->pos] == $tctv[0]) && ($this->string[$this->pos + 1] == $tctv[1])) {
                        return new Token($tctk);
                    }
                }
            }
    
            // starts with number, so must be number
            if (ctype_digit($this->string[$this->pos])) {
                return new Token(Token::Number, $this->fetchNumber());
            }
    
            // single character tokens
            if (($index = array_search($this->string[$this->pos], $this->single_tokens))) {
                ++$this->pos;
                return new Token($index);
            }
            
            // double quote = string
            if ($this->string[$this->pos] == '"') {
                $string = "";
    
                while ($this->string[++$this->pos] !== '"') {
                    if ($this->pos == $this->length) {
                        throw new LexerException("Unexpected end of data");
                    }
                    
                    if ($this->string[$this->pos] == "\n") {
                        throw new LexerException("Unexpected newline in string");
                    }
                    
                    $string .= $this->string[$this->pos];
                };
    
                ++$this->pos;
                return new Token(Token::String, $string);
            }
            
            // starts with @, so must be an annotation
            if ($this->string[$this->pos] == '@') {
                ++$this->pos;
                $string = "";
                
                while (($this->pos < $this->length) && (ctype_alnum($this->string[$this->pos]) || ($this->string[$this->pos] == '\\'))) {
                    $string .= $this->string[$this->pos++];
                }
    
                return new Token(Token::Annotation, $string);
            }

            // starts with $, so must be a variable
            if ($this->string[$this->pos] == '$') {
                ++$this->pos;
                $string = "";
                
                while (($this->pos < $this->length) && (ctype_alnum($this->string[$this->pos]) || in_array($this->string[$this->pos], ['_', '-']))) {
                    $string .= $this->string[$this->pos++];
                }
    
                return new Token(Token::Variable, $string);
            }

            // starts with letter, so must be a parameter
            if (ctype_alpha($this->string[$this->pos])) {
                $string = "";
    
                while (($this->pos < $this->length) && (ctype_alnum($this->string[$this->pos]) || in_array($this->string[$this->pos], ['_', '-']))) {
                    $string .= $this->string[$this->pos++];
                }
                
                if (strcasecmp($string, "true") == 0) {
                    return new Token(Token::True);
                } elseif (strcasecmp($string, "false") == 0) {
                    return new Token(Token::False);
                } else {
                    return new Token(Token::Parameter, $string);
                }
            }
            
            // error
			return new Token(Token::None, $this->string[$this->pos++]);
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
    }