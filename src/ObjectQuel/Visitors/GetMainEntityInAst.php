<?php
	
	
	namespace Services\ObjectQuel\Visitors;
	
	use Services\ObjectQuel\Ast\AstIn;
	use Services\ObjectQuel\AstInterface;
    use Services\ObjectQuel\Ast\AstIdentifier;
    use Services\ObjectQuel\AstVisitorInterface;
	
	/**
	 * Class GetMainEntityInAstException
	 */
	class GetMainEntityInAstException extends \Exception {
		/**
		 * Het opgeslagen AST-object dat aan de exception wordt meegegeven.
		 * @var AstInterface
		 */
		private AstInterface $astObject;
		
		/**
		 * Constructor voor GetMainEntityInAstException.
		 * @param AstInterface $astObject Het AST-object dat de hoofdentiteit vertegenwoordigt.
		 * @param string $message De foutmelding voor de exception (standaard leeg).
		 * @param int $code De foutcode voor de exception (standaard 0).
		 * @param \Throwable|null $previous Eventuele eerdere exception die deze exception heeft veroorzaakt.
		 */
		public function __construct(AstInterface $astObject, $message = "", $code = 0, \Throwable $previous = null) {
			// Roep de parent Exception constructor aan
			parent::__construct($message, $code, $previous);
			
			// Sla het AST-object op in een privé-eigenschap
			$this->astObject = $astObject;
		}
		
		/**
		 * Haal het opgeslagen AST-object op.
		 * @return AstInterface Het opgeslagen AST-object.
		 */
		public function getAstObject(): AstInterface {
			return $this->astObject;
		}
	}
	
	/**
	 * Class GetMainEntityInAst
	 * Identifies the first IN() clause used on the primary key
	 */
	class GetMainEntityInAst implements AstVisitorInterface {
		
		private AstIdentifier $primaryKey;
		
		/**
		 * ContainsRange constructor.
		 * @param AstIdentifier $primaryKey
		 */
		public function __construct(AstIdentifier $primaryKey) {
			$this->primaryKey = $primaryKey;
		}
		
		/**
		 * Loop door de AST en gooit een exception zodra de AstIn node voor de primary key is gevonden
		 * @param AstInterface $node
		 * @return void
		 * @throws \Exception
		 */
		public function visitNode(AstInterface $node): void {
			if (!$node instanceof AstIn) {
                return;
            }
            
            if ($node->getIdentifier()->getEntityOrParentIdentifier()->getRange()->getName() !== $this->primaryKey->getEntityOrParentIdentifier()->getRange()->getName()) {
				return;
			}
			
            if ($node->getIdentifier()->getName() !== $this->primaryKey->getName()) {
				return;
			}
			
			throw new GetMainEntityInAstException($node);
		}
	}