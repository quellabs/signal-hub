<?php
	
	namespace Quellabs\ObjectQuel\ObjectQuel;
	
	class LexerState {
		private int $pos;
		private int $previousPos;
		private int $previousPreviousPos;
		private int $lineNumber;
		private Token $next_token;
		private Token $lookahead;
		
		public function __construct(
			int    $pos,
			int    $previousPos,
			int    $previousPreviousPos,
			int    $lineNumber,
			Token  $next_token,
			Token  $lookahead,
		) {
			$this->pos = $pos;
			$this->previousPos = $previousPos;
			$this->previousPreviousPos = $previousPreviousPos;
			$this->lineNumber = $lineNumber;
			$this->next_token = $next_token;
			$this->lookahead = $lookahead;
		}
		
		public function getPos(): int {
			return $this->pos;
		}
		
		public function getPreviousPos(): int {
			return $this->previousPos;
		}
		
		public function getPreviousPreviousPos(): int {
			return $this->previousPreviousPos;
		}
		
		public function getLineNumber(): int {
			return $this->lineNumber;
		}
		
		public function getNextToken(): Token {
			return $this->next_token;
		}
		
		public function getLookahead(): Token {
			return $this->lookahead;
		}
	}