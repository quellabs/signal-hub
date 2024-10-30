<?php
	
	namespace Services\Kernel;
	
	interface ServiceInterface {
		public function supports(string $class): bool;
		public function getInstance(string $class, array $parameters = []): object;
	}