<?php
	
	namespace Services;
	
	/**
	 * Class Signal
	 * Deze klasse implementeert een signaal-slot mechanisme, vergelijkbaar met Qt's signalen en slots.
	 * Het stelt objecten in staat om te communiceren zonder dat ze direct van elkaar afhankelijk zijn.
	 */
	class Signal {
		
		// Array om de verwachte parameter types voor het signaal op te slaan
		private array $parameterTypes;
		
		// Array om verbindingen op te slaan (ontvangers en hun bijbehorende slots)
		private array $connections = [];
		
		/**
		 * Constructor om het signaal te initialiseren met parameter types
		 * @param array $parameterTypes De verwachte types van de parameters voor dit signaal
		 */
		public function __construct(array $parameterTypes) {
			$this->parameterTypes = $parameterTypes;
		}
		
		/**
		 * Normaliseert de typestring naar een consistente notatie
		 * @param string $type De ruwe type string
		 * @return string De genormaliseerde type string
		 */
		private function normalizeType(string $type): string {
			$typeMap = [
				'integer' => 'int',
				'boolean' => 'bool',
				'double'  => 'float',
			];
			
			return $typeMap[$type] ?? $type;
		}
		
		/**
		 * Verbindt een ontvanger object en zijn slotmethode aan dit signaal
		 * @param object $receiver Het object of de functie dat het signaal zal ontvangen
		 * @param string|null $slot De naam van de methode die zal worden aangeroepen
		 * @return void
		 * @throws \Exception Als er een type mismatch is of als de slotmethode niet bestaat
		 */
		private function connectObject(object $receiver, ?string $slot): void {
			// Controleer of er een slotmethode is meegegeven
			if ($slot === null) {
				throw new \Exception("Missing slot.");
			}
			
			// Controleer of de slotmethode bestaat op de ontvanger
			if (!method_exists($receiver, $slot)) {
				throw new \Exception("Slot {$slot} does not exist on receiver.");
			}
			
			// Kijk of de callable al in de lijst voorkomt. Zo ja, doe dan niets
			if (
				in_array($receiver, array_column($this->connections, 'receiver')) &&
				in_array($slot, array_column($this->connections, 'slot'))
			) {
				return;
			}
			
			// Haal reflectie van de slotmethode op
			$slotReflection = new \ReflectionMethod($receiver, $slot);
			$slotParams = $slotReflection->getParameters();
			
			// Controleer of het aantal parameters overeenkomt
			if (count($this->parameterTypes) !== count($slotParams)) {
				throw new \Exception("Signal and slot parameter count mismatch.");
			}
			
			// Controleer typecompatibiliteit voor elke parameter
			for ($i = 0; $i < count($this->parameterTypes); $i++) {
				$signalType = $this->parameterTypes[$i];
				$slotType = $slotParams[$i]->getType();
				
				if ($slotType === null) {
					throw new \Exception("Slot parameter {$i} is not typed.");
				}
				
				$slotTypeName = $slotType->getName();
				
				if (!$this->isTypeCompatible($signalType, $slotTypeName)) {
					throw new \Exception("Type mismatch for parameter {$i} between signal ({$signalType}) and slot ({$slotTypeName}).");
				}
			}
			
			// Voeg de verbinding toe aan de connections array
			$this->connections[] = ['receiver' => $receiver, 'slot' => $slot];
		}
		
		/**
		 * Verbindt een ontvanger functie aan dit signaal
		 * @param callable $receiver
		 * @return void
		 * @throws \ReflectionException|\Exception
		 */
		private function connectCallable(callable $receiver): void {
			// Kijk of de callable al in de lijst voorkomt. Zoja, doe dan niets
			if (in_array($receiver, array_column($this->connections, 'receiver'))) {
				return;
			}
			
			// Haal reflectie van de slotmethode op
			$slotReflection = new \ReflectionFunction($receiver);
			$slotParams = $slotReflection->getParameters();
			
			// Controleer of het aantal parameters overeenkomt
			if (count($this->parameterTypes) !== count($slotParams)) {
				throw new \Exception("Signal and slot parameter count mismatch.");
			}
			
			// Controleer typecompatibiliteit voor elke parameter
			for ($i = 0; $i < count($this->parameterTypes); $i++) {
				$signalType = $this->parameterTypes[$i];
				$slotType = $slotParams[$i]->getType();
				
				if ($slotType === null) {
					throw new \Exception("Slot parameter $i is not typed.");
				}
				
				$slotTypeName = $slotType->getName();
				
				if (!$this->isTypeCompatible($signalType, $slotTypeName)) {
					throw new \Exception("Type mismatch for parameter {$i} between signal ({$signalType}) and slot ({$slotTypeName}).");
				}
			}
			
			// Voeg de verbinding toe aan de connections array
			$this->connections[] = ['receiver' => $receiver, 'slot' => null];
		}
		
		/**
		 * Getter voor parameter types
		 * @return array De array met parameter types voor dit signaal
		 */
		public function getParameterTypes(): array {
			return $this->parameterTypes;
		}
		
		/**
		 * Getter voor alle connecties
		 * @return array De array met connecties voor dit signaal
		 */
		public function getConnections(): array {
			return $this->connections;
		}
		
		/**
		 * Controleert of het signaaltype compatibel is met het slottype
		 * @param string $signalType Het type van de signaalparameter
		 * @param string $slotType Het type van de slotparameter
		 * @return bool True als de types compatibel zijn, anders false
		 */
		public function isTypeCompatible(string $signalType, string $slotType): bool {
			$primitiveTypes = ['int', 'float', 'string', 'bool', 'array'];
			
			// Normaliseer de types
			$signalType = $this->normalizeType($signalType);
			$slotType = $this->normalizeType($slotType);
			
			// Als de types exact overeenkomen
			if ($signalType === $slotType) {
				return true;
			}
			
			// Controleer compatibiliteit van primitieve types
			$isSignalPrimitive = in_array($signalType, $primitiveTypes);
			$isSlotPrimitive = in_array($slotType, $primitiveTypes);
			
			if ($isSignalPrimitive && $isSlotPrimitive) {
				return false;  // Verschillende primitieve types zijn niet compatibel
			}
			
			if ($isSignalPrimitive || $isSlotPrimitive) {
				return false;  // Een primitief type is niet compatibel met een objecttype
			}
			
			// Op dit punt weten we dat beide types objecten zijn
			// Controleer of signalType een subklasse is van slotType of vice versa
			return is_subclass_of($signalType, $slotType) || is_subclass_of($slotType, $signalType);
		}
		
		/**
		 * Verbindt een ontvanger object en zijn slotmethode of functie aan dit signaal
		 * @param callable|object $receiver Het object of de functie dat het signaal zal ontvangen
		 * @param string|null $slot De naam van de methode die zal worden aangeroepen
		 * @throws \Exception Als er een type mismatch is of als de slotmethode niet bestaat
		 */
		public function connect(callable|object $receiver, ?string $slot = null): void {
			if (is_object($receiver) && !is_callable($receiver)) {
				$this->connectObject($receiver, $slot);
			} else {
				$this->connectCallable($receiver);
			}
		}
		
		/**
		 * Verbreekt de verbinding van een ontvanger en/of slot van dit signaal
		 * @param callable|object $receiver Het object of de callable waarvan de verbinding moet worden verbroken
		 * @param string|null $slot De naam van de slot waarvan de verbinding moet worden verbroken (optioneel)
		 */
		public function disconnect(callable|object $receiver, ?string $slot = null): void {
			$this->connections = array_filter($this->connections, function ($connection) use ($receiver, $slot) {
				// Als $receiver een callable is, verwijder de connectie als deze overeenkomt
				if (is_callable($receiver) && ($connection['receiver'] === $receiver)) {
					return false;
				}
				
				// Als $receiver een object is
				if (is_object($receiver) && ($connection['receiver'] === $receiver)) {
					// Als $slot null is, verwijder alle connecties van dit object
					if ($slot === null) {
						return false;
					}
					
					// Anders, verwijder alleen de connectie met de specifieke slot
					if ($connection['slot'] === $slot) {
						return false;
					}
				}
				
				// Behoud alle andere connecties
				return true;
			});
		}
		
		/**
		 * Zendt het signaal uit naar alle verbonden ontvangers
		 * @param mixed ...$args De argumenten die moeten worden doorgegeven aan de slotmethoden
		 * @throws \Exception Als er een type mismatch is of als het aantal argumenten niet overeenkomt
		 */
		public function emit(...$args): void {
			// Gooi exceptie als het aantal parameters niet overeenkomt
			if (count($args) !== count($this->parameterTypes)) {
				throw new \Exception("Argument count mismatch for signal emission.");
			}
			
			// Gooi exceptie als de parameter types niet overeenkomen
			foreach ($args as $index => $arg) {
				$expectedType = $this->parameterTypes[$index];
				$actualType = is_object($arg) ? get_class($arg) : gettype($arg);
				
				if (!$this->isTypeCompatible($actualType, $expectedType)) {
					throw new \Exception("Type mismatch for argument {$index} of signal emission.");
				}
			}
			
			// Roep de slots aan
			foreach ($this->connections as $connection) {
				$receiver = $connection['receiver'];
				$slot = $connection['slot'];
				
				if ($slot === null) {
					$receiver(...$args);
				} else {
					$receiver->$slot(...$args);
				}
			}
		}
	}