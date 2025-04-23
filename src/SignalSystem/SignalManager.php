<?php
	
	namespace Quellabs\ObjectQuel\SignalSystem;
	
	/**
	 * Signal manager for annotation events
	 */
	class SignalManager {
		
		/**
		 * @var array<string, Signal> Map of signal names to Signal objects
		 */
		protected array $signals = [];
		
		/**
		 * Get or create a signal by name
		 * @param string $signalName
		 * @param array $parameterTypes Parameter types for the signal
		 * @return Signal
		 */
		public function signal(string $signalName, array $parameterTypes): Signal {
			// Use existing signal if available
			if (isset($this->signals[$signalName])) {
				return $this->signals[$signalName];
			}
			
			// Create new signal with provided parameter types
			$this->signals[$signalName] = new Signal($parameterTypes);
			return $this->signals[$signalName];
		}
		
		/**
		 * Connect a slot to a signal
		 * @param string $signalPattern Signal name or pattern with wildcards
		 * @param callable|object $slot Slot to call
		 * @param string|null $method Method name if $slot is an object
		 * @param array|null $parameterTypes Required for new signals
		 * @param int $priority Higher priority slots execute first
		 * @return string Connection ID
		 * @throws \Exception
		 */
		public function connect(string $signalPattern, callable|object $slot, ?string $method = null, array $parameterTypes = null, int $priority = 0): string {
			// Create signal if it doesn't exist
			if (!isset($this->signals[$signalPattern])) {
				if ($parameterTypes === null) {
					throw new \InvalidArgumentException(
						"Cannot connect to non-existent signal '{$signalPattern}'. Provide parameter types."
					);
				}
				
				$this->signals[$signalPattern] = new Signal($parameterTypes);
			}
			
			return $this->signals[$signalPattern]->connect($slot, $method, $priority);
		}
		
		/**
		 * Disconnect a slot by connection ID
		 * @param string $signalPattern
		 * @param string $connectionId
		 * @return bool
		 */
		public function disconnect(string $signalPattern, string $connectionId): bool {
			if (!isset($this->signals[$signalPattern])) {
				return false;
			}
			
			return $this->signals[$signalPattern]->disconnect($connectionId);
		}
		
		/**
		 * Disconnect all connections for a receiver
		 * @param string $signalPattern
		 * @param object|callable $receiver
		 * @param string|null $slot
		 * @return int Number of disconnected connections
		 */
		public function disconnectReceiver(string $signalPattern, object|callable $receiver, ?string $slot = null): int {
			if (!isset($this->signals[$signalPattern])) {
				return 0;
			}
			
			return $this->signals[$signalPattern]->disconnectReceiver($receiver, $slot);
		}
		
		/**
		 * Check if a signal name matches a pattern with wildcards
		 * @param string $pattern The pattern with potential wildcards
		 * @param string $signalName The actual signal name to check
		 * @return bool
		 */
		private function matchesWildcard(string $pattern, string $signalName): bool {
			// If there's no wildcard, it's only a match if exact
			if (!str_contains($pattern, '*')) {
				return $pattern === $signalName;
			}
			
			// Convert the pattern to a regex
			$regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/';
			
			// Check if the signal name matches the pattern
			return (bool) preg_match($regex, $signalName);
		}
		
		/**
		 * Emit a signal with support for wildcard listeners
		 * @param string $signalName
		 * @param mixed ...$args
		 * @return void
		 */
		public function emit(string $signalName, ...$args): void {
			// Find all matching signals (exact or wildcard)
			foreach ($this->signals as $registeredPattern => $signal) {
				if ($this->matchesWildcard($registeredPattern, $signalName)) {
					$signal->emit(...$args);
				}
			}
		}
		
		/**
		 * Get all registered signals
		 * @return array<string, Signal>
		 */
		public function getSignals(): array {
			return $this->signals;
		}
	}