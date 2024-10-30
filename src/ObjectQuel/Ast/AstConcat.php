<?php
	
	namespace Services\ObjectQuel\Ast;
	
	use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class AstConcat
	 * Represents the SQL Concat keyword in the AST (Abstract Syntax Tree).
	 * @package Services\ObjectQuel\Ast
	 */
	class AstConcat extends Ast {
		
		/**
		 * Stores the list of parameters for the concat operation.
		 * @var array
		 */
		protected array $parameterList;
		
		/**
		 * AstConcat constructor.
		 * Initializes the node with an array of parameters for the concat operation.
		 * @param array $parameterList The list of parameters.
		 */
		public function __construct(array $parameterList) {
			$this->parameterList = $parameterList;
		}
		
		/**
		 * Method to accept an AstVisitor, part of the Visitor Pattern.
		 * @param AstVisitorInterface $visitor The visitor object.
		 */
		public function accept(AstVisitorInterface $visitor) {
			parent::accept($visitor);
			
			// Loop through each parameter and apply the visitor to it.
			foreach($this->parameterList as $item) {
				$this->accept($item);
			}
		}
		
		/**
		 * Retrieves the list of parameters used in the concat operation.
		 * @return array The list of parameters.
		 */
		public function getParameters(): array {
			return $this->parameterList;
		}
	}