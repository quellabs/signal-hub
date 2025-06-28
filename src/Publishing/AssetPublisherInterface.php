<?php

	namespace Quellabs\Contracts\Publishing;
	
	interface AssetPublisherInterface {
		
		/**
		 * Get a human-readable description of what this publisher does
		 */
		public static function getDescription(): string;
		
		/**
		 * Get the tag/name identifier for this publisher
		 */
		public static function getTag(): string;
		
		/**
		 * Publish the assets to the given base path
		 * @param string $basePath The project root directory
		 * @param bool $force Whether to overwrite existing files
		 * @return bool True if publishing succeeded, false otherwise
		 */
		public function publish(string $basePath, bool $force = false): bool;
		
		/**
		 * Get instructions to show to the user after successful publishing
		 * @return string[] Array of instruction strings
		 */
		public function getPostPublishInstructions(): array;
		
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