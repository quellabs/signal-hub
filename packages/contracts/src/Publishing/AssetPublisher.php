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
		 * @return string A unique identifier for this publisher (e.g., 'config', 'views', 'assets')
		 */
		public static function getTag(): string;
		
		/**
		 * Get a human-readable description of what this publisher does
		 * @return string A concise description of the publisher's functionality
		 */
		public static function getDescription(): string;
		
		/**
		 * Returns extended help information about what this publisher does
		 * @return string Detailed help text explaining the publisher functionality
		 */
		public static function getHelp(): string;
		
		/**
		 * Get the publishing manifest
		 * @return array Associative array containing publishing configuration and file mappings
		 */
		public function getManifest(): array;
		
		/**
		 * Get the source path for assets to be published
		 * @return string Absolute or relative path to the source directory
		 */
		public function getSourcePath(): string;
		
		/**
		 * Get instructions to show to the user after successful publishing
		 * @return string User-friendly instructions or information to display post-publish
		 */
		public function getPostPublishInstructions(): string;
		
		/**
		 * Check if this publisher can run (dependencies, requirements, etc.)
		 * @return bool True if the publisher can execute, false otherwise
		 */
		public function canPublish(): bool;
		
		/**
		 * Get reason why publisher can't run (if canPublish() returns false)
		 * @return string Human-readable explanation of why publishing cannot proceed
		 */
		public function getCannotPublishReason(): string;
	}