<?php
	
	namespace Quellabs\ObjectQuel\EntityManager;
	
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstBinaryOperator;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstBool;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstExpression;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstIdentifier;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstNumber;
	use Quellabs\ObjectQuel\ObjectQuel\Ast\AstString;
	use Quellabs\ObjectQuel\ObjectQuel\AstInterface;
	use Quellabs\ObjectQuel\ObjectQuel\QuelException;
	
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
		 * @param array $initialParams
		 * @return mixed The result of the evaluation
		 * @throws QuelException When an unknown AST node or operator is encountered
		 */
		public function evaluate(AstInterface $ast, array $row, array $initialParams=[]): mixed {
			switch(get_class($ast)) {
				case AstNumber::class:
				case AstString::class:
				case AstBool::class:
					return $ast->getValue();
				
				case AstIdentifier::class:
					return $row[$ast->getCompleteName()];
				
				case AstExpression::class:
					$left = $this->evaluate($ast->getLeft(), $row, $initialParams);
					$right = $this->evaluate($ast->getRight(), $row, $initialParams);
					
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
					$left = $this->evaluate($ast->getLeft(), $row, $initialParams);
					$right = $this->evaluate($ast->getRight(), $row, $initialParams);
					
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