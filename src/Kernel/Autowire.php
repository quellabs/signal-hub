<?php
	
	namespace Quellabs\ObjectQuel\Kernel;
	
	class Autowire {
		
		private Kernel $kernel;
		
		/**
		 * Autowire constructor
		 * @param Kernel $kernel
		 */
		public function __construct(Kernel $kernel) {
			$this->kernel = $kernel;
		}
		
		/**
		 * Verkrijgt de typehints en standaardwaarden voor parameters van een specifieke methode in een klasse.
		 * Dit is nuttig voor het inspecteren van methodes op runtime, om te begrijpen welke argumenten een methode
		 * verwacht, inclusief hun types en standaardwaarden, indien aanwezig.
		 * @param string $className De volledige naam van de klasse waarvan de methode geïnspecteerd moet worden.
		 * @param string $methodName De naam van de methode om te inspecteren. Als dit leeg is, wordt de constructor geïnspecteerd.
		 * @return array Een lijst met details van elke parameter, inclusief naam, type en standaardwaarde.
		 */
		private function getMethodTypeHints(string $className, string $methodName = ""): array {
			$result = [];
			
			try {
				// Bepaal of we de constructor of een specifieke methode inspecteren.
				$reflectionClass = new \ReflectionClass($className);
				$methodReflector = empty($methodName) ? $reflectionClass->getConstructor() : $reflectionClass->getMethod($methodName);
				
				if ($methodReflector) {
					foreach ($methodReflector->getParameters() as $parameter) {
						// Bepaal het type van de parameter, indien beschikbaar.
						$type = $parameter->hasType() ? $parameter->getType()->getName() : "";
						
						// Bepaal de standaardwaarde van de parameter, indien aanwezig.
						$defaultValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
						
						// Voeg de data toe aan het resultaat
						$result[] = [
							'name'          => $parameter->getName(),
							'type'          => $type,
							'default_value' => $defaultValue
						];
					}
				}
			} catch (\ReflectionException $e) {
			}
			
			return $result;
		}
		
		/**
		 * Use reflection to autowire service classes into the constructor of an object
		 * @param string $className
		 * @param string $methodName
		 * @param array $matchingVariables
		 * @return array
		 */
		public function autowireClass(string $className, string $methodName = "", array $matchingVariables = []): array {
			$passArguments = [];
			$methodTypeHints = $this->getMethodTypeHints($className, $methodName);
			
			foreach ($methodTypeHints as $typeHint) {
				if (!empty($typeHint["type"])) {
					if (!in_array($typeHint["type"], ["array", "string", "integer", "int", "float", "double", "boolean", "bool"])) {
						if ($typeHint["type"] === Kernel::class) {
							$passArguments[] = $this->kernel;
						} elseif (!empty($matchingVariables)) {
							$passArguments[] = $this->kernel->getFromProvider($typeHint["type"], $matchingVariables);
						} else {
							$passArguments[] = $this->kernel->getService($typeHint["type"]);
						}
					} elseif (isset($matchingVariables[$typeHint["name"]])) {
						$passArguments[] = $matchingVariables[$typeHint["name"]];
					} else {
						$passArguments[] = $typeHint["default"];
					}
				} elseif (isset($matchingVariables[$typeHint["name"]])) {
					$passArguments[] = $matchingVariables[$typeHint["name"]];
				} else {
					$passArguments[] = $typeHint["default"];
				}
			}
			
			return $passArguments;
		}
	}