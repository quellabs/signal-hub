<?php
	
	namespace Quellabs\ObjectQuel\Sculpt\Helpers;
	
	use Quellabs\ObjectQuel\Annotations\Orm\UniqueIndex;
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	use Quellabs\ObjectQuel\EntityStore;
	
	class IndexComparator {
		
		/**
		 * Database connection / interface with cakephp/database and Phinx
		 * @var DatabaseAdapter
		 */
		private DatabaseAdapter $connection;
		
		/**
		 * EntityStore manages entity metadata and relations
		 * @var EntityStore
		 */
		private EntityStore $entityStore;
		
		/**
		 * IndexComparator constructor
		 * @param DatabaseAdapter $connection
		 * @param EntityStore $entityStore
		 */
		public function __construct(DatabaseAdapter $connection, EntityStore $entityStore) {
			$this->connection = $connection;
			$this->entityStore = $entityStore;
		}
		
		/**
		 * Compares database indexes with entity-defined indexes to find missing or inconsistent indexes
		 *
		 * This method identifies:
		 * - Indexes that exist in the database but not in the entity
		 * - Indexes that exist in the entity but not in the database
		 * - Indexes that exist in both but have different configurations
		 *
		 * @param mixed $entity The entity class to analyze
		 * @return array An array containing differences between DB and entity indexes
		 */
		public function compareIndexes(mixed $entity): array {
			// Fetch the owning table of this entity
			$tableName = $this->entityStore->getOwningTable($entity);
			
			// Fetch the column map
			$columnMap = $this->entityStore->getColumnMap($entity);
			
			// Get database indexes
			$dbIndexes = $this->getTableIndexes($tableName);
			
			// Get entity indexes
			$entityIndexes = $this->getEntityIndexes($entity);

			// Early return if both are empty
			if (empty($dbIndexes) && empty($entityIndexes)) {
				return ['added' => [], 'modified' => [], 'deleted' => []];
			}
			
			// Normalize both sets of indexes to a common format for comparison
			$normalizedDbIndexes = $this->normalizeDbIndexes($dbIndexes);
			$normalizedEntityIndexes = $this->normalizeEntityIndexes($entityIndexes, $columnMap);
			
			// Initialize results arrays
			$result = [
				'added'    => [],
				'modified' => []
			];
			
			// Find missing and modified indexes in one pass through entity indexes
			foreach ($normalizedEntityIndexes as $name => $config) {
				if (!isset($normalizedDbIndexes[$name])) {
					$result['added'][$name] = $config;
				} elseif ($this->indexConfigDiffers($normalizedDbIndexes[$name], $config)) {
					$result['modified'][$name] = [
						'database' => $normalizedDbIndexes[$name],
						'entity'   => $config
					];
				}
				
				// Mark as processed
				unset($normalizedDbIndexes[$name]);
			}
			
			// Any remaining DB indexes must be deleted
			$result['deleted'] = $normalizedDbIndexes;
			return $result;
		}
		
		/**
		 * Normalizes database indexes to a common format for comparison
		 * @param array $dbIndexes Raw database indexes from Phinx
		 * @return array Normalized indexes
		 */
		private function normalizeDbIndexes(array $dbIndexes): array {
			$result = [];
			
			foreach ($dbIndexes as $name => $index) {
				$result[$name] = [
					'columns' => $index['columns'],
					'type'    => $index['type'],
					'unique'  => strtoupper($index['type']) === 'UNIQUE'
				];
			}
			
			return $result;
		}
		
		/**
		 * Normalizes entity annotation indexes to a common format for comparison
		 * @param array $entityIndexes Entity annotation indexes
		 * @param array $columnMap Array mapping property names to database columns
		 * @return array Normalized indexes
		 */
		private function normalizeEntityIndexes(array $entityIndexes, array $columnMap): array {
			$result = [];
			$columnMapKeys = array_keys($columnMap);
			
			foreach ($entityIndexes as $annotation) {
				$isUnique = $annotation instanceof UniqueIndex;
				$columns = $annotation->getColumns();

				$databaseColumns = [];
				foreach ($columns as $column) {
					$databaseColumns[] = $columnMap[$column];
				}
				
				$result[$annotation->getName()] = [
					'columns' => $databaseColumns,
					'type'    => $isUnique ? 'UNIQUE' : 'INDEX',
					'unique'  => $isUnique
				];
			}
			
			return $result;
		}
		
		/**
		 * Compares two index configurations to check if they differ
		 * @param array $dbConfig Database index configuration
		 * @param array $entityConfig Entity index configuration
		 * @return bool True if configurations differ, false otherwise
		 */
		private function indexConfigDiffers(array $dbConfig, array $entityConfig): bool {
			// Check column count first (quick fail)
			if (count($dbConfig['columns']) !== count($entityConfig['columns'])) {
				return true;
			}
			
			// Check uniqueness (single boolean comparison)
			if ($dbConfig['unique'] !== $entityConfig['unique']) {
				return true;
			}
			
			// Sort arrays before comparing to ensure consistent order
			$dbColumns = $dbConfig['columns'];
			$entityColumns = $entityConfig['columns'];
			sort($dbColumns);
			sort($entityColumns);
			
			// Direct array comparison is faster than array_diff for sorted arrays
			return $dbColumns !== $entityColumns;
		}
		
		/**
		 * Returns a list of indexes for a specified database table
		 * Just a renamed alias for the existing getIndexes method
		 * @param string $tableName The name of the table to retrieve indexes from
		 * @return array An associative array of indexes with their details
		 */
		private function getTableIndexes(string $tableName): array {
			return $this->connection->getIndexes($tableName);
		}
		
		/**
		 * Retrieves all index annotations defined for a given entity class
		 * Just a renamed alias for the existing getIndexes method
		 * @param mixed $entity The entity class to analyze
		 * @return array An array of Index and UniqueIndex annotation objects
		 */
		private function getEntityIndexes(mixed $entity): array {
			return $this->entityStore->getIndexes($entity);
		}
	}