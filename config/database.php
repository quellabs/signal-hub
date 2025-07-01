<?php
	
	/**
	 * Database User Settings
	 * This file returns database credentials with precedence: Environment > User Settings > Defaults
	 * Called by database-env.php
	 */
	
	// User-defined database settings (can be customized by users)
	$userSettings = [
		'driver'    => 'mysql',                    // Database driver (mysql, postgresql, sqlite, etc.)
		'host'      => 'localhost',                // Database server hostname or IP address
		'database'  => '',                         // Name of the database to connect to
		'username'  => '',                         // Database username for authentication
		'password'  => '',                         // Database password for authentication
		'port'      => 3306,                       // Database server port (3306 is MySQL default)
		'charset'   => 'utf8mb4',                  // Character set for database connection
		'collation' => 'utf8mb4_unicode_ci',       // Collation for text comparison and sorting
	];
	
	// Return database configuration with proper precedence
	return [
		'driver'    => $_ENV['DB_DRIVER'] ?? $userSettings['driver'] ?? null,
		'host'      => $_ENV['DB_HOST'] ?? $userSettings['host'] ?? null,
		'database'  => $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? $userSettings['database'] ?? null,
		'username'  => $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? $userSettings['username'] ?? null,
		'password'  => $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? $userSettings['password'] ?? null,
		'port'      => isset($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : ($userSettings['port'] ?? null),
		'charset'   => $_ENV['DB_CHARSET'] ?? $userSettings['charset'] ?? null,
		'collation' => $_ENV['DB_COLLATION'] ?? $userSettings['collation'] ?? null,
		
		'entity_namespace' => "App\\Entities",                                // Entity namespace
		'entity_path'      => dirname(__FILE__) . '/../src/Entities/',   // Path to the entities folder
		'migrations_path'  => dirname(__FILE__) . '/../migrations',      // Path to the migrations folder
	];