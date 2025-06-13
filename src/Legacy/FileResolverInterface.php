<?php
	
	namespace Quellabs\Canvas\Legacy;
	
	use Symfony\Component\HttpFoundation\Request;
	
	interface FileResolverInterface {

		/**
		 * Resolve a request path to a legacy file path
		 * Return null if this resolver cannot handle the path
		 */
		public function resolve(string $path, Request $request): ?string;
	}