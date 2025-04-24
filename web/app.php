<?php
	
	include_once("../src/Kernel/Kernel.php");
	include_once("../vendor/autoload.php");
	
	use Quellabs\ObjectQuel\EntityManager\Configuration;
	use Quellabs\ObjectQuel\Kernel\Kernel;
	
	use Symfony\Component\HttpFoundation\Request;
	
	$config = new Configuration();
	$config->setEntityPath(__DIR__ . '/../src/Entity');
	$config->setProxyDir(__DIR__ . '/../src/Proxies');
	$config->setCachePath(__DIR__ . '/../src/Cache');
	$config->setUseAnnotationCache(true);
	$config->setAnnotationCachePath(__DIR__ . '/../src/AnnotationCache');
	
	$config->setDatabaseParams(
		'mysql',                         // Driver
		$_ENV['DB_HOST'] ?? 'localhost', // Host
		$_ENV['DB_NAME'] ?? 'motorsportparts',// Database name
		$_ENV['DB_USER'] ?? 'root',   // Username
		$_ENV['DB_PASS'] ?? 'root',   // Password
		$_ENV['DB_PORT'] ?? 3306,        // Port
		$_ENV['DB_CHARSET'] ?? 'utf8mb4' // Character set
	);
	
	$kernel = new Kernel($config);
	$request = Request::createFromGlobals();
	$response = $kernel->handle($request);
	$response->send();