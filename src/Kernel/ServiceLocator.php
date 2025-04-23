<?php
	
	namespace Quellabs\ObjectQuel\Kernel;
	
	class ServiceLocator {
		
		private Kernel $kernel;
		private array $loadedContainers;
		
		/**
		 * @var serviceInterface[] $services
		 */
		private array $services;
		
		/**
		 * ServiceLocator constructor
		 */
		public function __construct(Kernel $kernel) {
			$this->kernel = $kernel;
			$this->loadedContainers = [];
			$this->services = [];
			
			$this->registerServices();
		}
		
		/**
		 * Scans all services in the src directory and puts them in a list
		 * @return void
		 */
		private function registerServices(): void {
			foreach($this->scanForServiceClasses(__DIR__ . DIRECTORY_SEPARATOR . "..") as $service) {
				$this->services[$service["class"]] = new $service["class"]($this->kernel);
			}
		}
		
		/**
		 * Scant recursief door een opgegeven directory op zoek naar service klassen.
		 * Een service klasse moet voldoen aan de volgende criteria:
		 * - De klasse moet in een eigen directory staan
		 * - De bestandsnaam moet gelijk zijn aan de directorynaam (bijv. /Service/Service.php)
		 * - De klasse moet de ServiceInterface implementeren
		 * @param string $baseDirectory Het absolute pad naar de basis directory om te scannen (bijv. __DIR__ . '/../src')
		 * @return array
		 */
		private function scanForServiceClasses(string $baseDirectory): array {
			// Controleer of de basis directory bestaat
			if (!is_dir($baseDirectory)) {
				return [];
			}
			
			// Haal alle directories op
			$serviceClasses = [];
			$directories = array_filter(glob($baseDirectory . '/*'), 'is_dir');
			
			foreach ($directories as $directory) {
				// Haal de directory naam op
				$dirName = basename($directory);
				
				// Construeer het verwachte bestandspad
				$expectedFile = $directory . '/' . $dirName . '.php';
				
				// Controleer of het bestand bestaat
				if (file_exists($expectedFile)) {
					// Bepaal de namespace + classname
					$relativePath = str_replace($baseDirectory . '/', '', $directory);
					$className = "Quellabs\\ObjectQuel\\" . str_replace('/', '\\', $relativePath) . '\\' . $dirName;
					
					try {
						// Laad het bestand
						require_once $expectedFile;
						
						// Controleer of de klasse bestaat en ServiceInterface implementeert
						if (class_exists($className)) {
							$reflection = new \ReflectionClass($className);
							
							if ($reflection->implementsInterface(ServiceInterface::class)) {
								$serviceClasses[] = [
									'path'  => $expectedFile,
									'class' => $className
								];
							}
						}
					} catch (\Throwable $e) {
						// Log de error en ga door met de volgende directory
						error_log("Error bij verwerken van $expectedFile: " . $e->getMessage());
						continue;
					}
				}
			}
			
			return $serviceClasses;
		}
		
		/**
		 * Fetches a container by name, either from a supporting service or by instantiating it
		 * @template T
		 * @param class-string<T> $serviceName The fully qualified class name of the container
		 * @return T|null The container instance
		 */
		public function getService(string $serviceName): ?object {
			// First try to get from existing loaded containers
			if (isset($this->loadedContainers[$serviceName])) {
				return $this->loadedContainers[$serviceName];
			}
			
			// Try to instantiate the container directly
			try {
				$container = new $serviceName(...$this->kernel->autowireClass($serviceName));
				$this->loadedContainers[$serviceName] = $container;
				return $container;
			} catch (\Throwable $e) {
				return null;
			}
		}
		
		/**
		 * Calls a service provider to fetch the desired object
		 * If that fails, try to find the Service
		 * @param string $className
		 * @param array $parameters
		 * @return object|null
		 */
		public function getFromProvider(string $className, array $parameters): ?object {
			foreach ($this->services as $provider) {
				if ($provider->supports($className)) {
					return $provider->getInstance($className, $parameters);
				}
			}
			
			return $this->getService($className);
		}
	}