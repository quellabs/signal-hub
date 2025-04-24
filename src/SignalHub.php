<?php
	
	namespace Quellabs\SignalHub;
	
	/**
	 * Central hub for managing signals - focused on registry and discovery
	 */
	class SignalHub {
		/**
		 * @var array Map of registered signals by object and name
		 */
		private array $registry = [];
		
		/**
		 * @var array Map of standalone signals (not owned by objects)
		 */
		private array $standaloneSignals = [];
		
		/**
		 * Check if a name matches a pattern with wildcards
		 * @param string $pattern Pattern with wildcards
		 * @param string $name Name to check
		 * @return bool
		 */
		private function matchesPattern(string $pattern, string $name): bool {
			// If there's no wildcard, it's only a match if exact
			if (!str_contains($pattern, '*')) {
				return $pattern === $name;
			}
			
			// Convert the pattern to a regex
			$regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], $pattern) . '$/';
			
			// Check if the name matches the pattern
			return (bool) preg_match($regex, $name);
		}
		
		/**
		 * Create a new standalone signal
		 * @param string $signalName Signal name/identifier
		 * @param array $parameterTypes Expected parameter types
		 * @return Signal The created signal
		 */
		public function createSignal(string $signalName, array $parameterTypes): Signal {
			$signal = new Signal($parameterTypes, $signalName);
			$this->standaloneSignals[$signalName] = $signal;
			return $signal;
		}
		
		/**
		 * Register a signal with the hub
		 * @param object|null $owner Owner object (can be null for standalone signals)
		 * @param string $name Signal name
		 * @param Signal $signal Signal object
		 * @return void
		 */
		public function registerSignal(?object $owner, string $name, Signal $signal): void {
			// Store as a standalone signal
			if ($owner === null) {
				$this->standaloneSignals[$name] = $signal;
				return;
			}
			
			// Store as an object-owned signal
			$ownerId = spl_object_id($owner);
			
			if (!isset($this->registry[$ownerId])) {
				$this->registry[$ownerId] = [
					'object' => $owner,
					'signals' => []
				];
			}
			
			$this->registry[$ownerId]['signals'][$name] = $signal;
		}
		
		/**
		 * Find signals matching a pattern
		 * @param string $pattern Signal name pattern with optional wildcards
		 * @return array<Signal> Array of matching signals
		 */
		public function findSignals(string $pattern): array {
			// Check standalone signals
			$result = array_filter($this->standaloneSignals, function ($name) use ($pattern) {
				return $this->matchesPattern($pattern, $name);
			}, ARRAY_FILTER_USE_KEY);
			
			// Check object signals
			foreach ($this->registry as $ownerId => $ownerData) {
				$ownerClass = get_class($ownerData['object']);
				
				foreach ($ownerData['signals'] as $signalName => $signal) {
					$qualifiedName = $ownerClass . '::' . $signalName;
					
					if ($this->matchesPattern($pattern, $qualifiedName)) {
						$result[$qualifiedName] = $signal;
					}
				}
			}
			
			return $result;
		}
		
		/**
		 * Get a standalone signal by name
		 * @param string $signalName
		 * @return Signal|null
		 */
		public function getStandaloneSignal(string $signalName): ?Signal {
			return $this->standaloneSignals[$signalName] ?? null;
		}
		
		/**
		 * Get a signal by owner and name
		 * @param object $owner Owner object
		 * @param string $name Signal name
		 * @return Signal|null
		 */
		public function getObjectSignal(object $owner, string $name): ?Signal {
			$ownerId = spl_object_id($owner);
			
			if (!isset($this->registry[$ownerId]) || !isset($this->registry[$ownerId]['signals'][$name])) {
				return null;
			}
			
			return $this->registry[$ownerId]['signals'][$name];
		}
		
		/**
		 * Get all registered signals
		 * @return array
		 */
		public function getAllSignals(): array {
			$result = [];
			
			// Add standalone signals
			foreach ($this->standaloneSignals as $name => $signal) {
				$result[] = [
					'name' => $name,
					'signal' => $signal,
					'paramTypes' => $signal->getParameterTypes(),
					'connections' => $signal->countConnections(),
					'standalone' => true
				];
			}
			
			// Add object signals
			foreach ($this->registry as $ownerId => $ownerData) {
				$ownerClass = get_class($ownerData['object']);
				
				foreach ($ownerData['signals'] as $signalName => $signal) {
					$result[] = [
						'owner' => $ownerData['object'],
						'class' => $ownerClass,
						'name' => $signalName,
						'signal' => $signal,
						'paramTypes' => $signal->getParameterTypes(),
						'connections' => $signal->countConnections(),
						'standalone' => false
					];
				}
			}
			
			return $result;
		}
	}