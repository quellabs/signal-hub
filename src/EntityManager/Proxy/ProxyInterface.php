<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Proxy;
	
	use Quellabs\ObjectQuel\EntityManager\EntityManager;
	
	interface ProxyInterface {
	
		public function __construct(EntityManager $entityManager);
		public function isInitialized(): bool;
		public function setInitialized(): void;

	}