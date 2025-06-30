<?php

	namespace Quellabs\Contracts\Publishing;
	
	/**
	 * Asset Publisher Interface
	 *
	 * Defines the contract for asset publishing implementations within the Quellabs framework.
	 * Asset publishers are responsible for deploying, copying, or otherwise making assets
	 * available in target locations or environments.
	 *
	 * Implementations of this interface should handle the publishing of various types of assets
	 * such as configuration files, templates, static resources, or any other project assets
	 * that need to be deployed or made available to the application.
	 */
	interface AssetPublisher {
		
		/**
		 * Get the tag/name identifier for this publisher
		 * @return string
		 */
		public static function getTag(): string;
		
		/**
		 * Get a human-readable description of what this publisher does
		 * @return string
		 */
		public static function getDescription(): string;
		
		/**
		 * Returns extended help information about what this publisher does
		 * @return string Detailed help text explaining the authentication publisher functionality
		 */
		public static function getHelp(): string;

		/**
		 * Publish the assets to the given base path
		 * @param string $basePath The project root directory
		 * @param bool $force Whether to overwrite existing files
		 * @return void
		 */
		public function publish(string $basePath, bool $force = false): void;
		
		/**
		 * Get instructions to show to the user after successful publishing
		 * @return string
		 */
		public function getPostPublishInstructions(): string;
		
		/**
		 * Check if this publisher can run (dependencies, requirements, etc.)
		 * @return bool
		 */
		public function canPublish(): bool;
		
		/**
		 * Get reason why publisher can't run (if canPublish() returns false)
		 * @return string
		 */
		public function getCannotPublishReason(): string;
	}