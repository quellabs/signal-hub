<?php
	
	namespace Services\Signalize;
	
	class SymbolTable {
		private array $symbols = [];
		
		public function __construct(array $symbols) {
			foreach ($symbols as $name => $type) {
				$this->declare($name, $type);
			}
		}
		
		public function declare($name, $type): void {
			$this->symbols[$name] = [
				'type'  => $type,
				'value' => ''
			];
		}

		public function has($name): bool {
			return isset($this->symbols[$name]);
		}
		
		public function get($name): mixed {
			return $this->has($name) ? $this->symbols[$name]["value"] : null;
		}

		public function set(string $name, mixed $value): void {
			if ($this->has($name)) {
				$this->symbols[$name]["value"] = $value;
			}
		}

		public function getType($name): mixed {
			return $this->has($name) ? $this->symbols[$name]["type"] : null;
		}
	}
