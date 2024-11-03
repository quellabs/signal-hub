<?php
	
	namespace Services\Signalize\Ast;
	
	use Services\Signalize\AstInterface;
	use Services\Signalize\AstVisitorInterface;
	
	/**
	 * Class AstFunctionCall
	 */
	class AstFunctionCall extends Ast {
		
		protected string $name;
		protected array $parameters;
		
		/**
		 * AstFunctionCall constructor.
		 * @param string $name
		 * @param array $parameters
		 */
		public function __construct(string $name, array $parameters) {
			$this->name = $name;
			$this->parameters = $parameters;
		}
		
		/**
		 * Accept a visitor to perform operations on this node.
		 * @param AstVisitorInterface $visitor The visitor to accept.
		 */
		public function accept(AstVisitorInterface $visitor): void {
			parent::accept($visitor);
			
			foreach($this->parameters as $parameter) {
				$parameter->accept($visitor);
			}
		}
		
		/**
		 * Get the name of the function being called
		 * @return string The left operand.
		 */
		public function getName(): string {
			return $this->name;
		}
		
		/**
		 * Get the parameters
		 * @return array
		 */
		public function getParameters(): array {
			return $this->parameters;
		}

		/**
		 * Set the parameters
		 * @return void
		 */
		public function setParameters(array $parameters): void {
			$this->parameters = $parameters;
		}
	}