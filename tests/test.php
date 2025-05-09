<?php
	
	require('../vendor/autoload.php');
	
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\Entity\ProductsDescriptionEntity;
	use Quellabs\ObjectQuel\Entity\ProductsEntity;
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
	
	$entity = $entityManager->find(ProductsEntity::class, 1492);
	$entity->setGuid('xyz1');
	$entityManager->persist($entity);
	$entityManager->flush($entity);
	
	
	//$result = $entityManager->findBy(ProductsEntity::class, ['guid' => '8ed51c45-e34c-4d5f-b29b-83a5ee0ecbe2']);
	

	/*
	$result = $entityManager->executeQuery("
		range of x is ProductsEntity
		retrieve (x) where x.productsId=1492
		sort by x.guid
	");
	
	foreach($result as $row) {
		$descriptions = $row['x']->getDescriptions();
		
		foreach($descriptions as $description) {
			echo $description->getProductsName() ."\n";
		}
	}
	*/
	
	
	