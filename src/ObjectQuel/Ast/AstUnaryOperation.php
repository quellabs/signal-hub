<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\AstVisitorInterface;
	
	class AstUnaryOperation extends Ast {
		private AstInterface $operand;
		private string $operator;
		
		public function __construct(AstInterface $operand, string $operator) {
			$this->operand = $operand;
			$this->operator = $operator;
			
			$operand->setParent($this);
		}
		
		public function getOperand(): AstInterface {
			return $this->operand;
		}
		
		public function getOperator(): string {
			return $this->operator;
		}
		
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			$this->operand->accept($visitor);
		}
		
	}
