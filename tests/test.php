<?php
	
	require('../vendor/autoload.php');
	
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\Entity\HamsterEntity;
	use Quellabs\ObjectQuel\EntityManager;
	
	$config = new Configuration();
	$config->setEntityPath(__DIR__ . '/../src/Entity');
	$config->setProxyDir(__DIR__ . '/../src/Proxies');
	$config->setUseMetadataCache(true);
	$config->setMetadataCachePath(__DIR__ . '/../src/AnnotationCache');
	
	$config->setDatabaseParams(
		'mysql',                         // Driver
		$_ENV['DB_HOST'] ?? 'localhost', // Host
		$_ENV['DB_NAME'] ?? 'motorsportparts',// Database name
		$_ENV['DB_USER'] ?? 'root',   // Username
		$_ENV['DB_PASS'] ?? 'root',   // Password
		$_ENV['DB_PORT'] ?? 3306,        // Port
		$_ENV['DB_CHARSET'] ?? 'utf8mb4' // Character set
	);
	
	$entityManager = new EntityManager($config);
	
	/**
	$result = $entityManager->executeQuery("
		range of main is HamsterEntit
		retrieve (main) where main.woopie = /^hallo/
	");
	 */
	
	$hamster = new HamsterEntity();
	$hamster->setWoopie('hallo2');
	$entityManager->persist($hamster);
	$entityManager->flush();
	

	/*
	$entity = $entityManager->find(HamsterEntity::class, 1);
	$entity->setWoopie('xyz');
	$entityManager->persist($entity);
	$entityManager->flush($entity);
	*/

	$entity = $entityManager->find(HamsterEntity::class, 1);
	$entityManager->remove($entity);
	$entityManager->flush($entity);
	
	
	
	
	