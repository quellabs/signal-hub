<?php
	
	include_once("../src/Kernel/Kernel.php");
	include_once("../vendor/autoload.php");
	
	use Quellabs\ObjectQuel\Kernel\Kernel;
	
	use Symfony\Component\HttpFoundation\Request;
	
	$kernel = new Kernel();
	$request = Request::createFromGlobals();
	
	$helper = new \Quellabs\ObjectQuel\AnnotationsReader\Helpers\UseStatementParser();
	$class = new \ReflectionClass(\Quellabs\ObjectQuel\EntityManager\EntityManager::class);
	$tmp = $helper->getImportsForClass($class);
	
	
	
	$response = $kernel->handle($request);
	$response->send();