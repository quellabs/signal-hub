<?php
	
	include_once("../src/Kernel/Kernel.php");
	include_once("../vendor/autoload.php");
	
	use Quellabs\ObjectQuel\EntityManager\Configuration;
	use Quellabs\ObjectQuel\Kernel\Kernel;
	
	use Symfony\Component\HttpFoundation\Request;
	
	$config = new Configuration();
	$config->setEntityPath(__DIR__ . '/../Entity');
	$config->setProxyDir(__DIR__ . '/../Proxies');
	$config->setCachePath(__DIR__ . '/../Cache');
	$config->setAnnotationCachePath(__DIR__ . '/../src/AnnotationCache');

	$kernel = new Kernel($config);
	$request = Request::createFromGlobals();
	$response = $kernel->handle($request);
	$response->send();