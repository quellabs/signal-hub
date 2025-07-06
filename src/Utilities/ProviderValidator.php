<?php
	
	namespace Quellabs\Discover\Utilities;
	
	use Psr\Log\LoggerInterface;
	use Quellabs\Contracts\Discovery\ProviderInterface;
	
	class ProviderValidator {
		
		/**
		 * Constants
		 */
		private const string CLASS_NAME_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*$/';
		
		/**
		 * Class used for logging
		 * @var LoggerInterface|null
		 */
		private ?LoggerInterface $logger;
		
		/**
		 * Context source for logger
		 * @var string
		 */
		private string $contextSource;
		
		/**
		 * ProviderValidator constructor
		 * @param LoggerInterface|null $logger
		 * @param string $contextSource
		 */
		public function __construct(
			?LoggerInterface $logger = null,
			string $contextSource = 'ProviderValidator'
		) {
			$this->logger = $logger;
			$this->contextSource = $contextSource;
		}
		
		/**
		 * Performs essential validation checks to ensure a provider class is properly
		 * defined and can be safely instantiated. This prevents runtime errors that
		 * would occur if invalid providers were included in the application bootstrap.
		 * @param string $providerClass Fully qualified class name of the provider to validate
		 * @return bool True if provider is valid and can be used, false if validation fails
		 */
		public function validate(string $providerClass): bool {
			// Prevent arbitrary class loading
			if (!preg_match(self::CLASS_NAME_PATTERN, $providerClass)) {
				$this->logger?->warning('Invalid provider class name rejected', [
					'scanner' => $this->contextSource,
					'class'   => $providerClass,
					'reason'  => 'invalid_class_name_format',
				]);
				
				return false;
			}
			
			// Verify that the provider class can be found and autoloaded
			// This catches typos in class names, missing files, or autoloader issues
			if (!class_exists($providerClass)) {
				$this->logger?->warning('Provider class not found during discovery', [
					'scanner' => $this->contextSource,
					'class'   => $providerClass,
					'reason'  => 'class_not_found'
				]);
				
				return false;
			}
			
			// Ensure the provider class implements the required ProviderInterface contract
			// This guarantees the class has all necessary methods for provider functionality
			if (!is_subclass_of($providerClass, ProviderInterface::class)) {
				$this->logger?->warning('Provider class does not implement required interface', [
					'scanner'            => $this->contextSource,
					'class'              => $providerClass,
					'reason'             => 'invalid_interface',
					'required_interface' => ProviderInterface::class,
				]);
				
				return false;
			}
			
			// Check if class is instantiable
			try {
				$reflection = new \ReflectionClass($providerClass);
				
				if (!$reflection->isInstantiable()) {
					$this->logger?->warning('Provider class is not instantiable', [
						'scanner'      => $this->contextSource,
						'class'        => $providerClass,
						'reason'       => 'not_instantiable',
						'is_abstract'  => $reflection->isAbstract(),
						'is_interface' => $reflection->isInterface(),
						'is_trait'     => $reflection->isTrait(),
					]);
					
					return false;
				}
			} catch (\ReflectionException $e) {
				$this->logger?->warning('Failed to analyze provider class with reflection', [
					'scanner' => $this->contextSource,
					'class'   => $providerClass,
					'reason'  => 'reflection_failed',
					'error'   => $e->getMessage(),
				]);
				
				return false;
			}
			
			// The provider passed all validation checks and is safe to instantiate
			return true;
		}
	}