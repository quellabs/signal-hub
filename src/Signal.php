<?php
	
	namespace Quellabs\SignalHub;
	
	/**
	 * Signal class for type-safe event handling in the annotations reader
	 */
	class Signal {
		
		/**
		 * @var array Parameter types expected by this signal
		 */
		private array $parameterTypes;
		
		/**
		 * @var array Connections (receivers and their slots)
		 */
		private array $connections = [];
		
		/**
		 * Constructor to initialize the signal with parameter types
		 * @param array $parameterTypes Expected parameter types for this signal
		 */
		public function __construct(array $parameterTypes) {
			$this->parameterTypes = $parameterTypes;
		}
		
		/**
		 * Normalizes the type string to a consistent notation
		 * @param string $type Raw type string
		 * @return string Normalized type string
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
		 * Checks if signal type is compatible with slot type
		 * @param string $signalType Signal parameter type
		 * @param string $slotType Slot parameter type
		 * @return bool True if types are compatible
		 */
		public function isTypeCompatible(string $signalType, string $slotType): bool {
			$primitiveTypes = ['int', 'float', 'string', 'bool', 'array'];
			
			// Normalize types
			$signalType = $this->normalizeType($signalType);
			$slotType = $this->normalizeType($slotType);
			
			// Exact match
			if ($signalType === $slotType) {
				return true;
			}
			
			// Check primitive type compatibility
			$isSignalPrimitive = in_array($signalType, $primitiveTypes);
			$isSlotPrimitive = in_array($slotType, $primitiveTypes);
			
			if ($isSignalPrimitive && $isSlotPrimitive) {
				return false;  // Different primitive types are not compatible
			}
			
			if ($isSignalPrimitive || $isSlotPrimitive) {
				return false;  // A primitive type is not compatible with an object type
			}
			
			// Both are object types - check inheritance
			return is_subclass_of($signalType, $slotType) || is_subclass_of($slotType, $signalType);
		}
		
		/**
		 * Connects an object and its slot method to this signal
		 * @param object $receiver Object that will receive the signal
		 * @param string|null $slot Method name to be called
		 * @return string Connection ID for later disconnection
		 * @throws \Exception If types mismatch or slot doesn't exist
		 */
		private function connectObject(object $receiver, ?string $slot): string {
			// Check if slot method is provided
			if ($slot === null) {
				throw new \Exception("Missing slot method name.");
			}
			
			// Check if slot method exists on receiver
			if (!method_exists($receiver, $slot)) {
				throw new \Exception("Slot {$slot} does not exist on receiver.");
			}
			
			// Get reflection of slot method
			$slotReflection = new \ReflectionMethod($receiver, $slot);
			$slotParams = $slotReflection->getParameters();
			
			// Check parameter count
			if (count($this->parameterTypes) !== count($slotParams)) {
				throw new \Exception("Signal and slot parameter count mismatch.");
			}
			
			// Check type compatibility for each parameter
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
			
			// Generate a unique connection ID
			$connectionId = uniqid('connection_', true);
			
			// Add connection
			$this->connections[$connectionId] = [
				'receiver' => $receiver,
				'slot' => $slot,
				'priority' => 0  // Default priority
			];
			
			return $connectionId;
		}
		
		/**
		 * Connects a callable to this signal
		 * @param callable $receiver Callable function to receive the signal
		 * @param int $priority Execution priority (higher executes first)
		 * @return string Connection ID
		 * @throws \Exception If types mismatch
		 */
		private function connectCallable(callable $receiver, int $priority = 0): string {
			// Get reflection of callable
			$slotReflection = new \ReflectionFunction($receiver);
			$slotParams = $slotReflection->getParameters();
			
			// Check parameter count
			if (count($this->parameterTypes) !== count($slotParams)) {
				throw new \Exception("Signal and slot parameter count mismatch.");
			}
			
			// Check type compatibility for each parameter
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
			
			// Generate a unique connection ID
			$connectionId = uniqid('connection_', true);
			
			// Add connection
			$this->connections[$connectionId] = [
				'receiver' => $receiver,
				'slot' => null,
				'priority' => $priority
			];
			
			return $connectionId;
		}
		
		/**
		 * Connects a receiver to this signal
		 * @param callable|object $receiver Object or callable to receive the signal
		 * @param string|null $slot Method name (if object)
		 * @param int $priority Execution priority (higher executes first)
		 * @return string Connection ID for later disconnection
		 * @throws \Exception If types mismatch or slot doesn't exist
		 */
		public function connect(callable|object $receiver, ?string $slot = null, int $priority = 0): string {
			// For objects with slot methods
			if (is_object($receiver) && !is_callable($receiver)) {
				$connectionId = $this->connectObject($receiver, $slot);
				$this->connections[$connectionId]['priority'] = $priority;
				
				// Sort connections by priority
				$this->sortConnectionsByPriority();
				
				return $connectionId;
			}
			
			// For callables
			$connectionId = $this->connectCallable($receiver, $priority);
			
			// Sort connections by priority
			$this->sortConnectionsByPriority();
			
			return $connectionId;
		}
		
		/**
		 * Sort connections by priority (higher first)
		 */
		private function sortConnectionsByPriority(): void {
			uasort($this->connections, function($a, $b) {
				return $b['priority'] <=> $a['priority'];
			});
		}
		
		/**
		 * Disconnects a receiver by connection ID
		 * @param string $connectionId ID returned from connect
		 * @return bool Whether disconnection was successful
		 */
		public function disconnect(string $connectionId): bool {
			if (!isset($this->connections[$connectionId])) {
				return false;
			}
			
			unset($this->connections[$connectionId]);
			return true;
		}
		
		/**
		 * Disconnects all connections for a receiver
		 * @param object|callable $receiver
		 * @param string|null $slot Method name (if object)
		 * @return int Number of disconnected connections
		 */
		public function disconnectReceiver(object|callable $receiver, ?string $slot = null): int {
			$disconnectedCount = 0;
			$connectionsToRemove = [];
			
			foreach ($this->connections as $id => $connection) {
				if ($connection['receiver'] === $receiver) {
					if ($slot === null || $connection['slot'] === $slot) {
						$connectionsToRemove[] = $id;
						$disconnectedCount++;
					}
				}
			}
			
			foreach ($connectionsToRemove as $id) {
				unset($this->connections[$id]);
			}
			
			return $disconnectedCount;
		}
		
		/**
		 * Emits the signal to all connected receivers
		 * @param mixed ...$args Arguments to pass to slots
		 * @throws \Exception If argument types or count mismatch
		 */
		public function emit(...$args): void {
			// Check argument count
			if (count($args) !== count($this->parameterTypes)) {
				throw new \Exception("Argument count mismatch for signal emission.");
			}
			
			// Check argument types
			foreach ($args as $index => $arg) {
				$expectedType = $this->parameterTypes[$index];
				$actualType = is_object($arg) ? get_class($arg) : gettype($arg);
				
				if (!$this->isTypeCompatible($actualType, $expectedType)) {
					throw new \Exception("Type mismatch for argument {$index} of signal emission.");
				}
			}
			
			// Call the slots
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
		
		/**
		 * Get parameter types for this signal
		 * @return array
		 */
		public function getParameterTypes(): array {
			return $this->parameterTypes;
		}
		
		/**
		 * Get all connections
		 * @return array
		 */
		public function getConnections(): array {
			return $this->connections;
		}
		
		/**
		 * Get the number of connections
		 * @return int
		 */
		public function countConnections(): int {
			return count($this->connections);
		}
	}