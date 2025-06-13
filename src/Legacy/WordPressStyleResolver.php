<?php
	
	namespace Quellabs\Canvas\Legacy;
	
	use Symfony\Component\HttpFoundation\Request;
	
	class WordPressStyleResolver implements FileResolverInterface {
		private string $legacyPath;
		
		public function __construct(string $legacyPath) {
			$this->legacyPath = $legacyPath;
		}
		
		public function resolve(string $path, Request $request): ?string {
			// Handle /2024/03/my-blog-post style URLs
			if (preg_match('#^/(\d{4})/(\d{2})/([^/]+)#', $path, $matches)) {
				$year = $matches[1];
				$month = $matches[2];
				$slug = $matches[3];
				
				$file = $this->legacyPath . "posts/{$year}-{$month}-{$slug}.php";
				
				return file_exists($file) ? $file : null;
			}
			
			return null;
		}
	}