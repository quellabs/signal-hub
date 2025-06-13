<?php
	
	namespace Quellabs\Canvas\Legacy;
	
	use Symfony\Component\HttpFoundation\Request;
	
	class DefaultFileResolver implements FileResolverInterface {
		
		private string $legacyPath;
		
		public function __construct(string $legacyPath) {
			$this->legacyPath = $legacyPath;
		}
		
		public function resolve(string $path, Request $request): ?string {
			$path = trim($path, '/');
			
			// Try these patterns in order:
			$candidates = [
				$this->legacyPath . $path . '.php',           // /users -> legacy/users.php
				$this->legacyPath . $path . '/index.php',     // /users -> legacy/users/index.php
			];
			
			foreach ($candidates as $file) {
				if (file_exists($file)) {
					return $file;
				}
			}
			
			return null;
		}
	}
