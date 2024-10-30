<?php
	
	namespace Services\Signalize;
	
	/**
	 * Variable scope manager
	 */
	class SymbolTable {
		private $scope_stack;
		private $variables;
		private $types;
		
		/**
		 * Scope constructor.
		 */
		public function __construct() {
			$this->scope_stack = [];
			$this->variables = [];
			$this->types = [];
		}
		
		/**
		 * Increases the scope
		 * @return void
		 */
		public function pushScope(array $variables=[]) {
			$this->scope_stack[] = [
				'variables' => [],
				'types'     => []
			];
		}
		
		/**
		 * Increases the scope
		 * @return void
		 */
		public function popScope() {
			array_pop($this->scope_stack);
		}
		
		/**
		 * Returns all internal types
		 * @return string[]
		 */
		public function getSimpleTypes(): array {
			return [
				'int',
				'float',
				'string',
				'bool'
			];
		}
		
		/**
		 * Returns all types
		 * @return array|int[]|string[]
		 */
		public function getTypeList(): array {
			return array_unique(array_merge(array_column($this->types, "type"), $this->getSimpleTypes()));
		}
		
		/**
		 * Adds a new variable
		 * @param string $type
		 * @param string $name
		 * @return int
		 */
		public function addVariable(string $type, string $name): int {
			$this->variables[] = ['type' => $type, 'name' => $name];
			$this->scope_stack[array_key_last($this->scope_stack)]["variables"][$name] = array_key_last($this->variables);
			return array_key_last($this->variables);
		}
		
		/**
		 * Returns true if the variable exists
		 * @param string $name
		 * @return bool
		 */
		public function variableExists(string $name): bool {
			foreach ($this->scope_stack as $scope) {
				if (array_key_exists($name, $scope["variables"])) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Returns true if the variable exists in the current scope
		 * @param string $name
		 * @return bool
		 */
		public function variableExistsInCurrentScope(string $name): bool {
			$scope = $this->scope_stack[array_key_last($this->scope_stack)];
			return array_key_exists($name, $scope["variables"]);
		}
		
		/**
		 * Returns the index of the variable
		 * @param string $name
		 * @return array|null
		 */
		public function getVariable(string $name): ?array {
			$scopeStackKeys = array_reverse(array_keys($this->scope_stack));
			
			foreach ($scopeStackKeys as $scopeStackKey) {
				$scope = $this->scope_stack[$scopeStackKey];
				
				if (isset($scope["variables"][$name])) {
					return $this->variables[$scope["variables"][$name]];
				}
			}
			
			return null;
		}
		
		/**
		 * Returns all variables present in the current scope
		 * @return array
		 */
		public function getVariablesInCurrentScope(): array {
			$variables = [];
			$scope = $this->scope_stack[array_key_last($this->scope_stack)];
			
			if (!empty($scope["variables"])) {
				foreach($scope["variables"] as $index) {
					$variables[] = $this->variables[$index];
				}
			}
			
			return $variables;
		}
		
		/**
		 * Returns all types present in the current scope
		 * @return array
		 */
		public function getTypesInCurrentScope(): array {
			$types = [];
			$scope = $this->scope_stack[array_key_last($this->scope_stack)];
			
			if (!empty($scope["types"])) {
				foreach($scope["types"] as $index) {
					$types[] = $this->types[$index];
				}
			}
			
			return $types;
		}
		
		/**
		 * Stores a new type
		 * @param string $typeName
		 * @param array $typeData
		 * @return void
		 */
		public function addType(string $typeName, array $typeData): int {
			$this->types[] = ['type' => $typeName, 'contents' => $typeData];
			$this->scope_stack[array_key_last($this->scope_stack)]["types"][$typeName] = array_key_last($this->types);
			return array_key_last($this->types);
		}
		
		/**
		 * Returns true if the type exists
		 * @param string $name
		 * @return bool
		 */
		public function typeExists(string $name): bool {
			foreach ($this->scope_stack as $scope) {
				if (array_key_exists($name, $scope["types"])) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Returns true if the type exists in the current scope
		 * @param string $name
		 * @return bool
		 */
		public function typeExistsInCurrentScope(string $name): bool {
			$scope = $this->scope_stack[array_key_last($this->scope_stack)];
			return array_key_exists($name, $scope["types"]);
		}
		
		/**
		 * Returns the type
		 * @param string $name
		 * @return array
		 */
		public function getType(string $name): array {
			$scopeStackKeys = array_reverse(array_keys($this->scope_stack));
			
			foreach ($scopeStackKeys as $scopeStackKey) {
				$scope = $this->scope_stack[$scopeStackKey];
				
				if (isset($scope["types"][$name])) {
					return $this->types[$scope["types"][$name]];
				}
			}
			
			return [];
		}
		
		/**
		 * Returns the type as a string
		 * @param string $name
		 * @return array|null
		 */
		public function getTypeAsString(string $name): ?string {
			$typeInfo = $this->getType($name);
			return !empty($typeInfo) ? json_encode($typeInfo) : null;
		}
	}