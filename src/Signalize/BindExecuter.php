<?php
	
	namespace Services\Signalize;
	
	class BindExecuter extends Executer {
		
		private array $map;
		private array $configData;
		private array $result;
		private string $event;
		
		/**
		 * BindExecuter constructor
		 * @param string $bytecode
		 * @param array $map
		 * @param array $flattenedConfigData
		 */
		public function __construct(string $event, string $bytecode, array $map=[], array $flattenedConfigData=[]) {
			$this->event = $event;
			$this->map = $map;
			$this->configData = $flattenedConfigData;
			$this->result = [];
			parent::__construct($bytecode);
		}
		
		/**
		 * Array search but with a callback
		 * @param array $arr
		 * @param callable $func
		 * @return mixed
		 */
		protected function arraySearchFunc(array $arr, callable $func): mixed {
			foreach ($arr as $key => $v) {
				if ($func($v)) {
					return $key;
				}
			}
			
			return false;
		}
		
		/**
		 * Returns the value of a given ConfigData item
		 * @param string $container
		 * @param string $key
		 * @return array|null
		 */
		private function getConfigItem(string $container, string $key): ?array {
			foreach ($this->configData as $item) {
				if (($item["container"] === $container) && ($item["key"] === $key)) {
					return $item;
				}
			}

			return null;
		}
		
		/**
		 * Returns the value of a given ConfigData item
		 * @param string $container
		 * @param string $key
		 * @return string|null
		 */
		private function getConfigValue(string $container, string $key): ?string {
			$firstItem = $this->getConfigItem($container, $key);
			
			if (!$firstItem || !isset($firstItem["value"])) {
				return null;
			}

			if (is_array($firstItem["value"])) {
				return $firstItem["value"][0]["translation"];
			}
			
			return (string)$firstItem["value"];
		}
		
		/**
		 * Execute function
		 * @param string $functionName
		 * @param array $parameters
		 * @return mixed
		 * @throws \Exception
		 */
		protected function handleFunctionCall(string $functionName, array $parameters): mixed {
			switch ($functionName) {
				case 'Write':
					return null;
				
				case 'WriteLn':
					return null;
				
				case 'GetSelectedOptionId' :
					$element = $this->getConfigItem($parameters[0], $parameters[1]);
					$selectedOption = $element["selectedOption"] ?? [];
					return (string)$selectedOption["id"] ?? "";

				case 'GetSelectedOptionExtraValue' :
					$element = $this->getConfigItem($parameters[0], $parameters[1]);
					$selectedOption = $element["selectedOption"];
					return (string)$selectedOption["options"][$parameters[2]["value"]] ?? "";
					
				case 'SetValue' :
					$container = $parameters[0];
					$key = $parameters[1];
						
					foreach ($this->configData as &$item) {
						if (($item["container"] === $container) && ($item["key"] === $key)) {
							$item["value"] = $parameters[2];
							break;
						}
					}
					
					return null;
					
				default:
					return parent::handleFunctionCall($functionName, $parameters);
			}
		}
		
		/**
		 * Behandelt configuratie-opzoekingen in de bytecode.
		 * @param string $bytecode De bytecode die een configuratie-opzoeking aangeeft.
		 * @return mixed De opgezochte configuratiewaarde.
		 */
		protected function handleConfigLookup(string $bytecode): mixed {
			// Controleer of de bytecode een punt (".") bevat, wat wijst op een genestelde configuratie.
			if (str_contains($bytecode, ".")) {
				$container = substr($bytecode, 1, strpos($bytecode, ".") - 1);
				$key = substr($bytecode, strpos($bytecode, ".") + 1);
				return $this->getConfigValue($container, $key);
			}
			
			// Als er geen punt in de bytecode zit, behandel het als een enkele sleutel.
			$key = substr($bytecode, 1);
			$splitIndex = $this->arraySearchFunc($this->map["st"], fn($e) => $e["key"] == $key);
			return $this->map["st"][$splitIndex]["value"] ?? "";
		}

		/**
		 * Voert de huidige bytecode-instructie uit en retourneert het resultaat.
		 * @return mixed Het resultaat van de uitgevoerde bytecode-instructie.
		 */
		public function executeByteCode(?string $event=null): mixed {
			// Haal de huidige bytecode op basis van de huidige positie.
			$currentBytecode = $this->bytecode[$this->pos];
			
			// Verwerk value bind
			if ($event == "click") {
				$this->executeByteCode();
				return null;
			}
			
			// Verwerk diverse binds
			if (in_array($event, ["visible", "enable", "options", "value"])) {
				return $this->executeByteCode();
			}
			
			// Verwerk "css", en "style" binds
			if (in_array($event, ["css", "style"])) {
				// Verhoog de positie en splits de bytecode om de optie namen te verkrijgen.
				$optionNames = json_decode($this->bytecode[$this->pos++], true);
				
				// Maak een array van resultaten door de bijbehorende bytecodes uit te voeren.
				$items = [];
				foreach($optionNames as $name) {
					$items[$name] = $this->executeByteCode();
				}
				
				return $items;
			}
			
			// Verwerk configuratie-opzoekingen met "@"
			if (str_starts_with($currentBytecode, "@")) {
				$bytecode = $this->bytecode[$this->pos++];
				return $this->handleConfigLookup($bytecode);
			}
			
			// Roep de executeByteCode-methode van de ouderklasse aan als er geen specifieke bytecode wordt herkend.
			return parent::executeByteCode();
		}
		
		/**
		 * Returns the parsed result
		 * @return array
		 */
		public function getResult(): array {
			return $this->result;
		}
	}