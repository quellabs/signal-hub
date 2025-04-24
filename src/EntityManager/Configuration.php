<?php
	
	namespace Quellabs\ObjectQuel\EntityManager;
	
	/**
	 * Configuration class for ObjectQuel ORM
	 * Holds database connection details and entity mapping information
	 */
	class Configuration implements \ArrayAccess {
		
		/**
		 * @var array Database connection parameters
		 */
		private array $connectionParams = [];
		
		/**
		 * @var string The DSN string for database connection
		 */
		private string $dsn = '';
		
		/**
		 * @var string Path to entity classes
		 */
		private string $entityPaths = '';
		
		/**
		 * @var bool Whether to use proxy objects for lazy loading
		 */
		private bool $useProxies = true;
		
		/**
		 * @var string Directory where proxy classes will be stored
		 */
		private string $proxyDir = '';
		
		/**
		 * @var string Namespace for proxy classes
		 */
		private string $proxyNamespace = 'Quellabs\\ObjectQuel\\Proxies';
		
		/**
		 * @var bool Whether to auto-generate proxy classes
		 */
		private bool $autoGenerateProxyClasses = true;
		
		/**
		 * @var string Cache directory for metadata
		 */
		private string $cacheDir = '';
		
		/**
		 * @var bool Whether to use metadata cache
		 */
		private bool $useMetadataCache = true;
		
		/**
		 * Constructor - optionally initialize with connection parameters
		 * @param array $connectionParams Database connection parameters
		 */
		public function __construct(array $connectionParams = []) {
			if (!empty($connectionParams)) {
				$this->connectionParams = $connectionParams;
				
				// If DB_DSN is provided, use it directly
				if (isset($connectionParams['DSN'])) {
					$this->dsn = $connectionParams['DSN'];
				}
			}
		}
		
		/**
		 * Set database connection parameters and generate DSN
		 * @param string $driver Database driver (mysql, pgsql, etc.)
		 * @param string $host Database host
		 * @param string $dbname Database name
		 * @param string $user Database user
		 * @param string $password Database password
		 * @param int $port Database port
		 * @param string $charset Character set (default: utf8mb4)
		 * @param array $options Additional connection options
		 * @return self
		 */
		public function setDatabaseParams(
			string $driver,
			string $host,
			string $dbname,
			string $user,
			string $password,
			int    $port = 3306,
			string $charset = 'utf8mb4',
			array  $options = []
		): self {
			$this->connectionParams = [
				'driver'   => $driver,
				'host'     => $host,
				'database' => $dbname,
				'username' => $user,
				'password' => $password,
				'port'     => $port,
				'encoding' => $charset,
				'flags'    => $options
			];
			
			// Generate DSN string for CakePHP
			$this->dsn = "{$driver}://{$user}:{$password}@{$host}:{$port}/{$dbname}?encoding={$charset}";
			$this->connectionParams['dsn'] = $this->dsn;
			
			return $this;
		}
		
		/**
		 * Set DSN string directly
		 *
		 * @param string $dsn Database connection string
		 * @return self
		 */
		public function setDsn(string $dsn): self {
			$this->dsn = $dsn;
			$this->connectionParams['dsn'] = $dsn;
			return $this;
		}
		
		/**
		 * Get DSN string
		 * @return string
		 */
		public function getDsn(): string {
			return $this->dsn;
		}
		
		/**
		 * Set all database connection parameters at once
		 * @param array $params Connection parameters
		 * @return self
		 */
		public function setConnectionParams(array $params): self {
			$this->connectionParams = $params;
			
			// If DB_DSN is provided, extract it
			if (isset($params['dsn'])) {
				$this->dsn = $params['dsn'];
				return $this;
			}
			
			if (isset($params['driver'], $params['host'], $params['database'], $params['username'], $params['password'])) {
				// Generate DSN from individual parameters
				$this->setDatabaseParams(
					$params['driver'],
					$params['host'],
					$params['database'],
					$params['username'],
					$params['password'],
					$params['port'] ?? 3306,
					$params['encoding'] ?? 'utf8mb4',
					$params['flags'] ?? []
				);
			}
			
			return $this;
		}
		
		/**
		 * Get database connection parameters
		 * @return array
		 */
		public function getConnectionParams(): array {
			return $this->connectionParams;
		}
		
		/**
		 * Add a path where entity classes can be found
		 * @param string $path Directory path or namespace
		 * @return self
		 */
		public function setEntityPath(string $path): self {
			$this->entityPaths = $path;
			return $this;
		}
		
		/**
		 * Get the configured entity path
		 * @return string
		 */
		public function getEntityPath(): string {
			return $this->entityPaths;
		}
		
		/**
		 * Set whether to use proxy objects for lazy loading
		 * @param bool $useProxies
		 * @return self
		 */
		public function setUseProxies(bool $useProxies): self {
			$this->useProxies = $useProxies;
			
			return $this;
		}
		
		/**
		 * Get whether to use proxy objects
		 * @return bool
		 */
		public function getUseProxies(): bool {
			return $this->useProxies;
		}
		
		/**
		 * Set directory where proxy classes will be stored
		 * @param string $proxyDir
		 * @return self
		 */
		public function setProxyDir(string $proxyDir): self {
			$this->proxyDir = $proxyDir;
			
			return $this;
		}
		
		/**
		 * Get proxy directory
		 * @return string
		 */
		public function getProxyDir(): string {
			return $this->proxyDir;
		}
		
		/**
		 * Set namespace for proxy classes
		 * @param string $proxyNamespace
		 * @return self
		 */
		public function setProxyNamespace(string $proxyNamespace): self {
			$this->proxyNamespace = $proxyNamespace;
			return $this;
		}
		
		/**
		 * Get proxy namespace
		 * @return string
		 */
		public function getProxyNamespace(): string {
			return $this->proxyNamespace;
		}
		
		/**
		 * Set whether to auto-generate proxy classes
		 * @param bool $autoGenerate
		 * @return self
		 */
		public function setAutoGenerateProxyClasses(bool $autoGenerate): self {
			$this->autoGenerateProxyClasses = $autoGenerate;
			return $this;
		}
		
		/**
		 * Get whether to auto-generate proxy classes
		 * @return bool
		 */
		public function getAutoGenerateProxyClasses(): bool {
			return $this->autoGenerateProxyClasses;
		}
		
		/**
		 * Set cache directory for metadata
		 * @param string $cacheDir
		 * @return self
		 */
		public function setCacheDir(string $cacheDir): self {
			$this->cacheDir = $cacheDir;
			
			return $this;
		}
		
		/**
		 * Get cache directory
		 * @return string
		 */
		public function getCacheDir(): string {
			return $this->cacheDir;
		}
		
		/**
		 * Set whether to use metadata cache
		 * @param bool $useCache
		 * @return self
		 */
		public function setUseMetadataCache(bool $useCache): self {
			$this->useMetadataCache = $useCache;
			return $this;
		}
		
		/**
		 * Get whether to use metadata cache
		 * @return bool
		 */
		public function getUseMetadataCache(): bool {
			return $this->useMetadataCache;
		}
		
		/**
		 * ArrayAccess implementation - Check if offset exists
		 * @param mixed $offset
		 * @return bool
		 */
		public function offsetExists(mixed $offset): bool {
			return isset($this->connectionParams[$offset]);
		}
		
		/**
		 * ArrayAccess implementation - Get value at offset
		 * @param mixed $offset
		 * @return mixed
		 */
		public function offsetGet(mixed $offset): mixed {
			return $this->connectionParams[$offset] ?? null;
		}
		
		/**
		 * ArrayAccess implementation - Set value at offset
		 * @param mixed $offset
		 * @param mixed $value
		 * @return void
		 */
		public function offsetSet(mixed $offset, mixed $value): void {
			$this->connectionParams[$offset] = $value;
			
			// Update DSN if DB_DSN is set
			if ($offset === 'DB_DSN') {
				$this->dsn = $value;
			}
		}
		
		/**
		 * ArrayAccess implementation - Unset offset
		 * @param mixed $offset
		 * @return void
		 */
		public function offsetUnset(mixed $offset): void {
			unset($this->connectionParams[$offset]);
		}
	}