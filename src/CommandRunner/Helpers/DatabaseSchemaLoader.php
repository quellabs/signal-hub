<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	
	class DatabaseSchemaLoader {
		private DatabaseAdapter $connection;
		private array $tableDefinitions = [];
		
		public function __construct(DatabaseAdapter $connection) {
			$this->connection = $connection;
		}
		
		public function fetchDatabaseTableSchema(string $tableName): array {
			if (!isset($this->tableDefinitions[$tableName])) {
				$columns = $this->connection->getColumnsEx($tableName);
				
				if (empty($columns)) {
					return [];
				}
				
				$this->tableDefinitions[$tableName] = $columns;
			}
			
			return $this->tableDefinitions[$tableName];
		}
	}