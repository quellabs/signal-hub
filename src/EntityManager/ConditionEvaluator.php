<?php
	
	namespace Services\EntityManager;
	
	use Services\ObjectQuel\Ast\AstBinaryOperator;
	use Services\ObjectQuel\Ast\AstBool;
	use Services\ObjectQuel\Ast\AstExpression;
	use Services\ObjectQuel\Ast\AstIdentifier;
	use Services\ObjectQuel\Ast\AstNumber;
	use Services\ObjectQuel\Ast\AstString;
	use Services\ObjectQuel\AstInterface;
	use Services\ObjectQuel\QuelException;
	
	/**
	 * Responsible for evaluating conditions in AST nodes against data rows
	 *
	 * This class provides functionality to evaluate various types of AST nodes
	 * against data rows, enabling condition checking in both query execution
	 * and result joining operations.
	 */
	class ConditionEvaluator {
		
		/**
		 * Evaluates a condition AST against a data row
		 *
		 * @param AstInterface $ast The AST condition to evaluate
		 * @param array $row The data row to evaluate against
		 * @return mixed The result of the evaluation
		 * @throws QuelException When an unknown AST node or operator is encountered
		 */
		public function evaluate(AstInterface $ast, array $row): mixed {
			switch(get_class($ast)) {
				case AstNumber::class:
				case AstString::class:
				case AstBool::class:
					return $ast->getValue();
				
				case AstIdentifier::class:
					return $row[$ast->getCompleteName()];
				
				case AstExpression::class:
					$left = $this->evaluate($ast->getLeft(), $row);
					$right = $this->evaluate($ast->getRight(), $row);
					
					return match ($ast->getOperator()) {
						'=' => $left == $right,
						'<>', '!=' => $left != $right,
						'<' => $left < $right,
						'>' => $left > $right,
						'<=' => $left <= $right,
						'>=' => $left >= $right,
						default => throw new QuelException("Unknown operator {$ast->getOperator()}"),
					};
				
				case AstBinaryOperator::class:
					$left = $this->evaluate($ast->getLeft(), $row);
					$right = $this->evaluate($ast->getRight(), $row);
					
					return match ($ast->getOperator()) {
						'AND' => $left && $right,
						'OR' => $left || $right,
						default => throw new QuelException("Unknown operator {$ast->getOperator()}"),
					};
				
				default:
					throw new QuelException("Unknown AST node " . get_class($ast));
			}
		}
	}