<?php
	
	namespace Quellabs\Discover\Composer;
	
	use Composer\Plugin\PluginInterface;
	use Composer\EventDispatcher\EventSubscriberInterface;
	use Composer\Composer;
	use Composer\IO\IOInterface;
	use Composer\Script\Event;
	
	class ServiceDiscoveryPlugin implements PluginInterface, EventSubscriberInterface {
		
		/**
		 * @var Composer
		 */
		private Composer $composer;
		
		/**
		 * @var IOInterface
		 */
		private IOInterface $io;
		
		public function activate(Composer $composer, IOInterface $io): void {
			$this->composer = $composer;
			$this->io = $io;
		}
		
		public function deactivate(Composer $composer, IOInterface $io) {
			// Cleanup if needed
		}
		
		public function uninstall(Composer $composer, IOInterface $io) {
			// Cleanup if needed
		}
		
		public static function getSubscribedEvents(): array {
			return [
				'post-install-cmd' => 'onPostInstall',
				'post-update-cmd'  => 'onPostUpdate',
			];
		}
		
		public function onPostInstall(Event $event) {
			$this->generateServiceMap($event->getComposer(), $event->getIO());
		}
		
		public function onPostUpdate(Event $event) {
			$this->generateServiceMap($event->getComposer(), $event->getIO());
		}
		
		private function generateServiceMap(Composer $composer, IOInterface $io): void {
			try {
				$io->write('<info>Generating package extra data map...</info>');
				
				// Get the lock file data
				$locker = $composer->getLocker();
				
				if (!$locker->isLocked()) {
					$io->writeError('<warning>No composer.lock file found. Skipping service map generation.</warning>');
					return;
				}
				
				$lockData = $locker->getLockData();
				$extraMap = $this->extractServiceMap($lockData);
				
				if (empty($extraMap)) {
					$io->write('<comment>No packages with "extra" data found.</comment>');
					return;
				}
				
				// Determine output path from config
				$outputPath = $this->getOutputPath($composer);
				
				if ($outputPath === null) {
					$io->write('<comment>No discovery-mapping output-file configured. Skipping generation.</comment>');
					return;
				}
				
				// Ensure directory exists
				$outputDir = dirname($outputPath);
				
				if (!is_dir($outputDir)) {
					mkdir($outputDir, 0755, true);
				}
				
				// Generate the extra map file
				$this->writeExtraMapFile($outputPath, $extraMap);
				
				$io->write("<info>Extra map generated successfully at: {$outputPath}</info>");
				$io->write("<comment>Found " . count($extraMap) . " packages with extra data.</comment>");
				
			} catch (\Exception $e) {
				$io->writeError("<error>Failed to generate service map: {$e->getMessage()}</error>");
			}
		}
		
		/**
		 * Get the output path for the extra map file
		 * @param Composer $composer
		 * @return string|null
		 */
		private function getOutputPath(Composer $composer): ?string {
			// Check for custom configuration in composer.json extra section
			$extra = $composer->getPackage()->getExtra();
			
			if (isset($extra['discovery-mapping']['output-file'])) {
				$outputFile = $extra['discovery-mapping']['output-file'];
				
				// If relative path, make it relative to project root
				if (!$this->isAbsolutePath($outputFile)) {
					$projectRoot = dirname($composer->getConfig()->getConfigSource()->getName());
					$outputFile = $projectRoot . '/' . ltrim($outputFile, '/');
				}
				
				return $outputFile;
			}
			
			// No configuration found - do not generate file
			return null;
		}
		
		/**
		 * Check if a path is absolute
		 * @param string $path
		 * @return bool
		 */
		private function isAbsolutePath(string $path): bool {
			// Unix/Linux absolute path
			if (str_starts_with($path, '/')) {
				return true;
			}
			
			// Windows absolute path (C:\ or C:/)
			if (preg_match('/^[a-zA-Z]:[\/\\\\]/', $path)) {
				return true;
			}
			
			return false;
		}
		
		/**
		 * Extract extra data from lock file
		 * @param array $lockData
		 * @return array
		 */
		private function extractServiceMap(array $lockData): array {
			$extraMap = [];
			
			// Process main packages only (no dev packages)
			if (isset($lockData['packages'])) {
				foreach ($lockData['packages'] as $package) {
					$this->processPackageExtra($package, $extraMap);
				}
			}
			
			return $extraMap;
		}
		
		/**
		 * Process a single package for extra data
		 * @param array $package
		 * @param array &$extraMap
		 */
		private function processPackageExtra(array $package, array &$extraMap): void {
			$packageName = $package['name'] ?? null;
			
			if (!$packageName) {
				return;
			}
			
			// Get the complete extra block
			$extra = $package['extra'] ?? [];
			
			if (!empty($extra)) {
				$extraMap[$packageName] = $extra;
			}
		}
		
		/**
		 * Write the extra map to a PHP file
		 * @param string $outputPath
		 * @param array $extraMap
		 */
		private function writeExtraMapFile(string $outputPath, array $extraMap): void {
			$timestamp = date('Y-m-d H:i:s');
			$count = count($extraMap);
			
			$content = <<<PHP
<?php
/**
 * Auto-generated package extra data map
 * Generated on: {$timestamp}
 * Total packages with extra data: {$count}
 *
 * This file is automatically generated by the ServiceDiscoveryPlugin.
 * Do not edit manually as changes will be overwritten.
 *
 * Format: "package_name" => complete extra block
 */

return
PHP;
			
			$content .= $this->varExportPretty($extraMap, 0);
			$content .= ";\n";
			
			if (file_put_contents($outputPath, $content) === false) {
				throw new \RuntimeException("Failed to write extra map to: {$outputPath}");
			}
		}
		
		/**
		 * Pretty print var_export with proper indentation
		 * @param mixed $var
		 * @param int $indent
		 * @return string
		 */
		private function varExportPretty($var, int $indent = 0): string {
			$indentStr = str_repeat('    ', $indent);
			
			if (is_array($var)) {
				if (empty($var)) {
					return '[]';
				}
				
				$isAssoc = array_keys($var) !== range(0, count($var) - 1);
				$output = "[\n";
				
				foreach ($var as $key => $value) {
					$output .= $indentStr . '    ';
					if ($isAssoc) {
						$output .= var_export($key, true) . ' => ';
					}
					$output .= $this->varExportPretty($value, $indent + 1);
					$output .= ",\n";
				}
				
				$output .= $indentStr . ']';
				return $output;
			}
			
			return var_export($var, true);
		}
	}