<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	
	/**
	 * Class responsible for loading and caching database schema information
	 * Used for retrieving column definitions for database tables
	 */
	class DatabaseSchemaLoader {

		/** @var DatabaseAdapter Database connection interface */
		private DatabaseAdapter $connection;
		
		/** @var array Cache of table column definitions indexed by table name */
		private array $tableDefinitions = [];
		
		/**
		 * Constructor - initializes the loader with a database connection
		 * @param DatabaseAdapter $connection Database adapter for executing queries
		 */
		public function __construct(DatabaseAdapter $connection) {
			$this->connection = $connection;
		}
		
		/**
		 * Fetches the schema information for a specified table
		 * Caches results to avoid repeated database queries
		 * @param string $tableName The name of the table to get schema for
		 * @return array Array of column definitions, empty if table not found
		 */
		public function fetchDatabaseTableSchema(string $tableName): array {
			// Check if the schema is already cached
			if (!isset($this->tableDefinitions[$tableName])) {
				// Query database for column information
				$columns = $this->connection->getColumnsEx($tableName);
				
				// If no columns found, return an empty array
				if (empty($columns)) {
					return [];
				}
				
				// Cache the column definitions for future use
				$this->tableDefinitions[$tableName] = $columns;
			}
			
			// Return cached column definitions
			return $this->tableDefinitions[$tableName];
		}
	}