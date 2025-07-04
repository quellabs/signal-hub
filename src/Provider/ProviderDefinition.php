<?php
	
	namespace Quellabs\Discover\Provider;
	
	use InvalidArgumentException;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	
	/**
	 * Simple immutable value object for provider definitions
	 * Replaces arrays with type-safe objects
	 */
	readonly class ProviderDefinition {
		public string $className;
		public string $family;
		public ?string $configFile;
		public array $metadata;
		public array $defaults;
		
		/**
		 * ProviderDefinition constructor
		 * @param string $className
		 * @param string $family
		 * @param string|null $configFile
		 * @param array $metadata
		 * @param array $defaults
		 */
		public function __construct(
			string  $className,
			string  $family,
			?string $configFile = null,
			array   $metadata = [],
			array   $defaults = []
		) {
			$this->defaults = $defaults;
			$this->metadata = $metadata;
			$this->configFile = $configFile;
			$this->family = $family;
			$this->className = $className;
			
			if (empty($this->className)) {
				throw new InvalidArgumentException('Provider class name cannot be empty');
			}
			
			if (empty($this->family)) {
				throw new InvalidArgumentException('Provider family cannot be empty');
			}
		}
		
		/**
		 * Create from array (backward compatibility)
		 * @param array $data
		 * @return self
		 */
		public static function fromArray(array $data): self {
			if (!isset($data['class']) || !isset($data['family'])) {
				throw new InvalidArgumentException('Missing required class or family');
			}
			
			return new self(
				className: $data['class'],
				family: $data['family'],
				configFile: $data['config'] ?? null,
				metadata: $data['metadata'] ?? [],
				defaults: $data['defaults'] ?? []
			);
		}
		
		/**
		 * Convert to array (for caching/serialization)
		 * @return array
		 */
		public function toArray(): array {
			return [
				'class'    => $this->className,
				'family'   => $this->family,
				'config'   => $this->configFile,
				'metadata' => $this->metadata,
				'defaults' => $this->defaults
			];
		}
		
		/**
		 * Generate unique key
		 * @return string
		 */
		public function getKey(): string {
			return $this->family . '::' . $this->className;
		}
		
		/**
		 * Check if belongs to family
		 * @param string $family
		 * @return bool
		 */
		public function belongsToFamily(string $family): bool {
			return $this->family === $family;
		}
		
		/**
		 * Check if the definition has a config file
		 * @return bool
		 */
		public function hasConfigFile(): bool {
			return $this->configFile !== null;
		}
		
		/**
		 * Validate the provider class
		 * @return bool
		 */
		public function isValidClass(): bool {
			return
				class_exists($this->className) &&
				is_subclass_of($this->className, ProviderInterface::class);
		}
	}