<?php
	
	namespace Quellabs\ObjectQuel\Sculpt\Commands;
	
	use Quellabs\AnnotationReader\AnnotationReader;
	use Quellabs\Discover\Provider\ProviderInterface;
	use Quellabs\ObjectQuel\Configuration;
	use Quellabs\ObjectQuel\DatabaseAdapter\DatabaseAdapter;
	use Quellabs\ObjectQuel\EntityStore;
	use Quellabs\ObjectQuel\OrmException;
	use Quellabs\ObjectQuel\Sculpt\Helpers\EntitySchemaAnalyzer;
	use Quellabs\ObjectQuel\Sculpt\Helpers\PhinxMigrationBuilder;
	use Quellabs\Sculpt\CommandBase;
	use Quellabs\Sculpt\ConfigurationManager;
	use Quellabs\Sculpt\Console\ConsoleInput;
	use Quellabs\Sculpt\Console\ConsoleOutput;
	
	/**
	 * MakeMigration - CLI command for generating database migrations
	 *
	 * This command uses the EntitySchemaAnalyzer to detect differences between entity definitions
	 * and the database schema, then uses PhinxMigrationBuilder to create migration files that
	 * synchronize the database with entity changes.
	 */
	class MakeMigrationsCommand extends CommandBase {
		private ?DatabaseAdapter $connection = null;
		private ?AnnotationReader $annotationReader = null;
		private ?EntityStore $entityStore = null;
		private string $entityPath;
		private string $migrationsPath;
		private Configuration $configuration;
		
		/**
		 * MakeEntityCommand constructor
		 * @param ConsoleInput $input
		 * @param ConsoleOutput $output
		 * @param ProviderInterface|null $provider
		 * @throws OrmException
		 */
		public function __construct(ConsoleInput $input, ConsoleOutput $output, ?ProviderInterface $provider = null) {
			parent::__construct($input, $output, $provider);
			$this->configuration = $provider->getConfiguration();
			$this->entityPath = $this->configuration->getEntityPath();
			$this->migrationsPath = $this->configuration->getMigrationsPath();
		}
		
		/**
		 * Execute the database migration generation command
		 * @param ConfigurationManager $config Parameters passed to the command
		 * @return int Exit code (0 for success, 1 for failure)
		 */
		public function execute(ConfigurationManager $config): int {
			$this->output->writeLn("Generating database migrations based on entity changes...");
			
			// Initialize the entity schema analyzer
			$entitySchemaAnalyzer = new EntitySchemaAnalyzer(
				$this->getConnection(),
				$this->getAnnotationReader(),
				$this->getEntityStore(),
				$this->entityPath
			);
			
			// Step 1: Scan and validate entities
			$entityClasses = $entitySchemaAnalyzer->scanEntityClasses();
			
			if (empty($entityClasses)) {
				$this->output->writeLn("No entity classes found.");
				return 1;
			}
			
			// Step 2: Analyze changes between entities and database
			$allChanges = $entitySchemaAnalyzer->analyzeEntityChanges($entityClasses);
			
			// Step 3: Generate a migration file based on changes
			$migrationBuilder = new PhinxMigrationBuilder($this->migrationsPath);
			$result = $migrationBuilder->generateMigrationFile($allChanges);
			
			if (!$result['success']) {
				$this->output->writeLn($result['message']);
				return 1;
			}
			
			$this->output->writeLn($result['message'] . ": " . $result['path']);
			return 0;
		}
		
		/**
		 * Get the command signature/name for registration in the CLI
		 * @return string Command signature
		 */
		public function getSignature(): string {
			return "make:migrations";
		}
		
		/**
		 * Get a short description of what the command does
		 * @return string Command description
		 */
		public function getDescription(): string {
			return "Generate database migrations based on entity changes";
		}
		
		/**
		 * Get detailed help information for the command
		 * @return string Command help text
		 */
		public function getHelp(): string {
			return "Creates a new database migration file by comparing entity definitions with current database schema to synchronize changes.";
		}
		
		/**
		 * Returns the database connector
		 * @return DatabaseAdapter
		 */
		private function getConnection(): DatabaseAdapter {
			if ($this->connection === null) {
				$this->connection = new DatabaseAdapter($this->configuration);
			}
			
			return $this->connection;
		}
		
		/**
		 * Returns the AnnotationReader object
		 * @return AnnotationReader
		 */
		private function getAnnotationReader(): AnnotationReader {
			if ($this->annotationReader === null) {
				$annotationReaderConfiguration = new \Quellabs\AnnotationReader\Configuration();
				$annotationReaderConfiguration->setUseAnnotationCache($this->configuration->useMetadataCache());
				$annotationReaderConfiguration->setAnnotationCachePath($this->configuration->getMetadataCachePath());
				
				$this->annotationReader = new AnnotationReader($annotationReaderConfiguration);
			}
			
			return $this->annotationReader;
		}
		
		/**
		 * Returns the EntityStore object
		 * @return EntityStore
		 */
		private function getEntityStore(): EntityStore {
			if ($this->entityStore === null) {
				$this->entityStore = new EntityStore($this->configuration);
			}
			
			return $this->entityStore;
		}
	}