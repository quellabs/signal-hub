<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	use Quellabs\ObjectQuel\EntityManager\Configuration;
	use Quellabs\ObjectQuel\EntityManager\Core\EntityStore;
	
	/**
	 * EntityScanner
	 *
	 * Scans the entity directory to find available entities and their properties
	 * Leverages the EntityStore to get detailed information about primary keys
	 */
	class EntityScanner {
		/**
		 * @var Configuration
		 */
		private Configuration $configuration;
		
		/**
		 * @var EntityStore
		 */
		private EntityStore $entityStore;
		
		/**
		 * Constructor
		 *
		 * @param Configuration $configuration
		 * @param EntityStore $entityStore
		 */
		public function __construct(Configuration $configuration, EntityStore $entityStore) {
			$this->configuration = $configuration;
			$this->entityStore = $entityStore;
		}
		
		/**
		 * Get a list of all available entities in the entity path
		 * @return array Array of entity names without "Entity" suffix
		 */
		public function getAvailableEntities(): array {
			$entityPath = $this->configuration->getEntityPath();
			$entities = [];
			
			if (!is_dir($entityPath)) {
				return $entities;
			}
			
			$files = scandir($entityPath);
			
			foreach ($files as $file) {
				// Skip directories and non-php files
				if (is_dir($entityPath . '/' . $file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
					continue;
				}
				
				// Extract entity name without "Entity" suffix and .php extension
				$entityName = pathinfo($file, PATHINFO_FILENAME);
				if (str_ends_with($entityName, 'Entity')) {
					$entityName = substr($entityName, 0, -6);
					$entities[] = $entityName;
				}
			}
			
			return $entities;
		}
		
		/**
		 * Get primary key properties for an entity using EntityStore
		 *
		 * @param string $entityName Entity name without "Entity" suffix
		 * @return array Array of primary key property names
		 */
		public function getEntityPrimaryKeys(string $entityName): array {
			$fullEntityName = $this->configuration->getEntityNameSpace() . '\\' . $entityName . 'Entity';
			
			// Use the EntityStore to get primary keys if possible
			if ($this->entityStore->exists($fullEntityName)) {
				return $this->entityStore->getIdentifierKeys($fullEntityName);
			}
			
			// Fallback to looking for 'id' property if EntityStore doesn't have the entity
			return ['id'];
		}
		
		/**
		 * Get column names for the primary keys of an entity
		 * @param string $entityName Entity name without "Entity" suffix
		 * @return array Array of column names for primary keys
		 */
		public function getEntityPrimaryKeyColumns(string $entityName): array {
			$fullEntityName = $this->configuration->getEntityNameSpace() . '\\' . $entityName . 'Entity';
			
			// Use the EntityStore to get column names if possible
			if ($this->entityStore->exists($fullEntityName)) {
				return $this->entityStore->getIdentifierColumnNames($fullEntityName);
			}
			
			// Fallback to default 'id' column
			return ['id'];
		}
		
		/**
		 * Get all properties for an entity
		 * @param string $entityName Entity name without "Entity" suffix
		 * @return array Array of property information
		 */
		public function getEntityProperties(string $entityName): array {
			$fullEntityName = $this->configuration->getEntityNameSpace() . '\\' . $entityName . 'Entity';
			
			// Use the EntityStore to get all property information
			if ($this->entityStore->exists($fullEntityName)) {
				$properties = [];
				$columnMap = $this->entityStore->getColumnMap($fullEntityName);
				$primaryKeys = array_flip($this->entityStore->getIdentifierKeys($fullEntityName));
				
				foreach ($columnMap as $property => $column) {
					$properties[] = [
						'name'         => $property,
						'column'       => $column,
						'isPrimaryKey' => isset($primaryKeys[$property])
					];
				}
				
				return $properties;
			}
			
			return [];
		}
		
		/**
		 * Suggest an appropriate name for the inverse side of a bidirectional relationship
		 * @param string $entityName The entity name
		 * @param string $relationshipType The type of relationship
		 * @return string Suggested field name
		 */
		public function suggestInverseFieldName(string $entityName, string $relationshipType): string {
			// For OneToMany, suggest a plural form of the entity name
			if ($relationshipType === 'OneToMany') {
				return lcfirst($entityName) . 's';
			}
			
			// For ManyToOne and OneToOne, suggest the entity name
			return lcfirst($entityName);
		}
		
		/**
		 * Get all relationship types for a given entity
		 * @param string $entityName Entity name without "Entity" suffix
		 * @return array Array of relationships
		 */
		public function getEntityRelationships(string $entityName): array {
			$fullEntityName = $this->configuration->getEntityNameSpace() . '\\' . $entityName . 'Entity';
			
			if ($this->entityStore->exists($fullEntityName)) {
				$relationships = [];
				
				// Get all OneToOne relationships
				$oneToOne = $this->entityStore->getOneToOneDependencies($fullEntityName);
				
				foreach ($oneToOne as $property => $annotation) {
					$relationships[$property] = [
						'type'         => 'OneToOne',
						'targetEntity' => $this->getShortEntityName($annotation->getTargetEntity()),
						'mappedBy'     => $annotation->getMappedBy(),
						'inversedBy'   => $annotation->getInversedBy()
					];
				}
				
				// Get all OneToMany relationships
				$oneToMany = $this->entityStore->getOneToManyDependencies($fullEntityName);
				
				foreach ($oneToMany as $property => $annotation) {
					$relationships[$property] = [
						'type' => 'OneToMany',
						'targetEntity' => $this->getShortEntityName($annotation->getTargetEntity()),
						'mappedBy' => $annotation->getMappedBy()
					];
				}
				
				// Get all ManyToOne relationships
				$manyToOne = $this->entityStore->getManyToOneDependencies($fullEntityName);
				
				foreach ($manyToOne as $property => $annotation) {
					$relationships[$property] = [
						'type' => 'ManyToOne',
						'targetEntity' => $this->getShortEntityName($annotation->getTargetEntity()),
						'inversedBy' => $annotation->getInversedBy()
					];
				}
				
				return $relationships;
			}
			
			return [];
		}
		
		/**
		 * Convert full entity name to short name (without namespace and without 'Entity' suffix)
		 * @param string $fullEntityName Full entity name with namespace
		 * @return string Short entity name without namespace and 'Entity' suffix
		 */
		private function getShortEntityName(string $fullEntityName): string {
			// Remove namespace
			$parts = explode('\\', $fullEntityName);
			$entityName = end($parts);
			
			// Remove 'Entity' suffix if present
			if (str_ends_with($entityName, 'Entity')) {
				$entityName = substr($entityName, 0, -6);
			}
			
			return $entityName;
		}
	}