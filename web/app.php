<?php
	
	include_once("../src/Kernel/Kernel.php");
	include_once("../vendor/autoload.php");
	
	use Services\Kernel\Kernel;
	
	use Symfony\Component\HttpFoundation\Request;
	
	$kernel = new Kernel();
	$request = Request::createFromGlobals();
	$response = $kernel->handle($request);
	$response->send();