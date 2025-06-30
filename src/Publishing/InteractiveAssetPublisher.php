<?php
	
	namespace Quellabs\Contracts\Publishing;
	
	use Quellabs\Contracts\IO\ConsoleInput;
	use Quellabs\Contracts\IO\ConsoleOutput;
	
	/**
	 * Interface for asset publishers that support interactive console operations.
	 *
	 * This interface extends the base AssetPublisher to add interactive capabilities,
	 * allowing implementations to prompt users for input and display output during
	 * the publishing process. This is useful for publishers that need to ask for
	 * confirmation, display progress, or gather additional information from users.
	 */
	interface InteractiveAssetPublisher extends AssetPublisher {
		
		/**
		 * Set the console input and output handlers for interactive operations.
		 * @param ConsoleInput $input   Handler for reading user input from console
		 * @param ConsoleOutput $output Handler for writing output to console
		 * @return void
		 */
		public function setIO(ConsoleInput $input, ConsoleOutput $output): void;
	}