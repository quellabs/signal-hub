<?php
	
	namespace Quellabs\Canvas\Sculpt;
	
	use Quellabs\Discover\Discover;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Contracts\CommandBase;
	use Quellabs\Discover\Scanner\ComposerScanner;
	use Quellabs\Contracts\Publishing\AssetPublisher;
	use Quellabs\Contracts\Publishing\InteractiveAssetPublisher;
	
	class PublishCommand extends CommandBase {
		
		/**
		 * Returns the signature of this command
		 * @return string
		 */
		public function getSignature(): string {
			return "canvas:publish";
		}
		
		/**
		 * Returns a brief description of what this command is for
		 * @return string
		 */
		public function getDescription(): string {
			return "Publishes assets from available publishers";
		}
		
		/**
		 * Execute the publish command
		 *
		 * This is the main entry point for the canvas:publish command. It handles four scenarios:
		 * 1. --list flag: Shows all available publishers
		 * 2. --help flag: Shows detailed help for a specific tag or general usage
		 * 3. --tag=<name> option: Publishes assets for a specific tag
		 * 4. No parameters: Shows usage help
		 *
		 * @param ConfigurationManager $config Configuration containing command flags and options
		 * @return int Exit code (0 = success, 1 = error)
		 */
		public function execute(ConfigurationManager $config): int {
			// Show title of command
			$this->output->writeLn("<info>Canvas Publish Command</info>");
			$this->output->writeLn("");

			// Initialize the discovery system to find all available asset publishers
			$discover = new Discover();
			$discover->addScanner(new ComposerScanner("publishers"));
			$discover->discover();
			
			// Retrieve all discovered publisher providers from the discovery system
			$providers = $discover->getProviders();
			
			// Handle the --list flag first (no tag validation needed)
			if ($config->hasFlag("list")) {
				return $this->listPublishers($providers);
			}
			
			// Get the tag parameter once
			$tag = $config->get("tag");
			
			// If no tag is provided, show usage help
			if (!$tag) {
				$this->showUsageHelp();
				return 0;
			}
			
			// Validate the tag exists before doing anything else
			if (!$this->findProviderByTag($providers, $tag)) {
				$this->output->error("Publisher with tag '{$tag}' not found.");
				return 1;
			}
			
			// Now that we know the tag is valid, handle help or publish
			if ($config->hasFlag("help")) {
				return $this->showHelp($providers, $tag);
			}
			
			// Proceed with publishing
			return $this->publishTag($providers, $tag, $config->hasFlag("force"));
		}
		
		/**
		 * Show help information for publishers
		 * @param array $providers Array of discovered publisher providers
		 * @param string|null $tag Optional tag to show specific help for
		 * @return int Exit code (0 = success, 1 = error)
		 */
		private function showHelp(array $providers, ?string $tag = null): int {
			// Show help for a specific publisher tag
			$provider = $this->findProviderByTag($providers, $tag);
			
			// Display detailed help for the specific publisher
			$this->output->writeLn("<info>Help for publisher: {$tag}</info>");
			$this->output->writeLn($provider::getDescription());
			$this->output->writeLn("Usage: php ./vendor/bin/sculpt canvas:publish --tag={$tag}");
			$this->output->writeLn("");
			
			// Show extended help if the publisher supports it
			$helpText = $provider::getHelp();
			
			if (!empty($helpText)) {
				$this->output->writeLn($provider::getHelp());
				$this->output->writeLn("");
			}
			
			return 0;
		}
		
		/**
		 * Find a provider by its tag identifier
		 * @param array $providers Array of provider class names
		 * @param string $tag Tag to search for
		 * @return AssetPublisher|null Provider class if found, null otherwise
		 */
		private function findProviderByTag(array $providers, string $tag): ?AssetPublisher {
			foreach ($providers as $provider) {
				if ($provider::getTag() === $tag) {
					return $provider;
				}
			}
			
			return null;
		}
		
		/**
		 * List all available publishers
		 * @param array $providers
		 * @return int
		 */
		private function listPublishers(array $providers): int {
			$this->output->writeLn("<info>Available Publishers:</info>");
			$this->output->writeLn("");
			
			foreach ($providers as $provider) {
				$this->output->writeLn(sprintf(
					"  <comment>%s</comment> - %s",
					$provider->getTag(),
					$provider->getDescription()
				));
			}
			
			$this->output->writeLn("");
			return 0;
		}
		
		/**
		 * Publish assets for a specific tag
		 * @param array $providers
		 * @param string $tag
		 * @param bool $force
		 * @return int
		 */
		private function publishTag(array $providers, string $tag, bool $force = false): int {
			$targetProvider = $this->findProviderByTag($providers, $tag);
			
			// Check if the assets can be published
			if (!$targetProvider->canPublish()) {
				$this->showCannotPublishError($targetProvider);
				return 1;
			}
			
			// Show a message
			$this->output->writeLn("Publishing: {$tag}");
			$this->output->writeLn("Description: " . $targetProvider::getDescription());
			
			// Show a message with more details
			if ($targetProvider instanceof InteractiveAssetPublisher) {
				// Set IO in provider class
				$targetProvider->setIO($this->input, $this->output);
				
				// Show a message that the user can expect questions
				$this->output->writeLn("This publisher will ask you some configuration questions...");
			} elseif (!$force && !$this->askForConfirmation()) {
				return 0;
			}
			
			// Discover utility to fetch the project root
			$discover = new Discover();
			
			// Delegate publishing to the publisher class
			$targetProvider->publish($discover->getProjectRoot(), $force);
			$this->output->writeLn("<info>Assets published successfully!</info>");
			$this->output->writeLn($targetProvider->getPostPublishInstructions());
			return 0;
		}
		
		/**
		 * Display comprehensive usage help for the canvas:publish command
		 * @return void
		 */
		private function showUsageHelp(): void {
			$this->output->writeLn("<comment>Use publish to add assets using configured publishers</comment>");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>USAGE:</info>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish [options]");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>OPTIONS:</info>");
			$this->output->writeLn("  <comment>--list</comment>              List all available publishers (tags)");
			$this->output->writeLn("  <comment>--tag=PUBLISHER</comment>      Publish assets using a specific publisher");
			$this->output->writeLn("  <comment>--force</comment>              Skip all interactive prompts and confirmations");
			$this->output->writeLn("  <comment>--help</comment>               Display this help message");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>NOTE:</info>");
			$this->output->writeLn("  Publishers and tags refer to the same thing - configured publish targets.");
			$this->output->writeLn("");
			
			$this->output->writeLn("<info>EXAMPLES:</info>");
			$this->output->writeLn("  <comment># List all available publishers</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish --list");
			$this->output->writeLn("");
			$this->output->writeLn("  <comment># Publish using a specific publisher</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish --tag=production");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish --tag=staging");
			$this->output->writeLn("");
			$this->output->writeLn("  <comment># Skip interactive prompts (non-interactive mode)</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish --tag=production --force");
			$this->output->writeLn("");
			$this->output->writeLn("  <comment># Show help information</comment>");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish");
			$this->output->writeLn("  php ./vendor/bin/sculpt canvas:publish --tag=production --help");
		}
		
		/**
		 * Displays an error message when asset publishing fails
		 * @param AssetPublisher $targetProvider The asset publisher that failed to publish
		 * @return void
		 */
		private function showCannotPublishError(AssetPublisher $targetProvider): void {
			// Display the main error message to the user
			$this->output->error("Cannot publish assets");
			
			// Add a blank line for better readability
			$this->output->writeLn("");
			
			// Display the specific reason why publishing failed, obtained from the target provider
			$this->output->writeLn($targetProvider->getCannotPublishReason());
		}
		
		/**
		 * Prompts the user for confirmation before proceeding with an action.
		 * @return bool Returns true if user confirms with 'y', false otherwise.
		 */
		private function askForConfirmation(): bool {
			$confirmation = $this->input->ask("Proceed? (y/N)");
			
			// Check if the user entered 'y' (case-insensitive)
			if (strtolower($confirmation) === 'y') {
				return true; // User confirmed - proceed with the action
			}
			
			// Any input other than 'y' is treated as cancellation
			$this->output->writeLn("Cancelled.");
			return false; // User canceled or provided invalid input
		}
	}