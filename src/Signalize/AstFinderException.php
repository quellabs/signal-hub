<?php
	
	namespace Services\Signalize;
	
	/**
	 * Class AstFinderException
	 * @package Services\ObjectQuel
	 */
	class AstFinderException extends \Exception {

		private AstInterface $data;
		
		/**
		 * Redefine the exception so message isn't optional
		 * @param string $message
		 * @param int $code
		 * @param \Throwable|null $previous
		 * @param AstInterface|null $data
		 */
		public function __construct(string $message, $code = 0, \Throwable $previous = null, ?AstInterface $data=null) {
			$this->data = $data;
			parent::__construct($message, $code, $previous);
		}
		
		/**
		 * Get the data associated with the exception
		 * @return AstInterface|null
		 */
		public function getData(): ?AstInterface {
			return $this->data;
		}
	}