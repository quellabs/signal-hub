# ObjectQuel

![ObjectQuel Logo](https://placeholder-for-logo.png)

[![Latest Version](https://img.shields.io/packagist/v/quellabs/objectquel.svg)](https://packagist.org/packages/quellabs/objectquel)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/quellabs/objectquel.svg)](https://packagist.org/packages/quellabs/objectquel)

ObjectQuel is a powerful Object-Relational Mapping (ORM) system that revolutionizes database interaction through its data mapper pattern implementation, intuitive query language, and efficient architecture. Built on CakePhp's robust database foundation ([CakePhp Database](https://book.cakephp.org/4/en/orm/database-basics.html)), it delivers a distinctive approach to data management that separates your domain objects from persistence concerns while maintaining developer-friendly simplicity.

## Table of Contents

- [The ObjectQuel Advantage](#the-objectquel-advantage)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Components](#core-components)
- [Configuration](#configuration)
- [Working with Entities](#working-with-entities)
- [The ObjectQuel Language](#the-objectquel-language)
- [Entity Relationships](#entity-relationships)
- [Saving and Persisting Data](#saving-and-persisting-data)
- [Using Repositories](#using-repositories)
- [Utility Tools](#utility-tools)
- [Query Optimization](#query-optimization)
- [License](#license)

## The ObjectQuel Advantage

ObjectQuel addresses fundamental design challenges in object-relational mapping through its architecture:

- **Clean Domain Models**: True separation of concerns with the data mapper pattern
- **Intuitive Queries**: The ObjectQuel language provides an intuitive, object-oriented syntax for database operations that feels natural to developers
- **Relationship Simplicity**: Work with complex relationships without complex query code
- **Performance By Design**: Multiple built-in optimization strategies for efficient database interactions
- **Hybrid Data Sources**: Uniquely combine traditional databases with external JSON data sources

## Installation

Installation can be done through composer.

```bash
composer require quellabs/objectquel
```

## Quick Start

This shows a quick way to use ObjectQuel:

```php
// 1. Create configuration
$config = new Configuration();
$config->setDsn('mysql://db_user:db_password@localhost:3306/my_database');
$config->setEntityNamespace('App\\Entity');
$config->setEntityPath(__DIR__ . '/src/Entity');

// 2. Create EntityManager
$entityManager = new EntityManager($config);

// 3. Find an entity
$product = $entityManager->find(\App\Entity\ProductEntity::class, 101);

// 4. Update and save
$product->setPrice(29.99);
$entityManager->persist($product);
$entityManager->flush();

// 5. Query using ObjectQuel language
$results = $entityManager->executeQuery("
    range of p is App\\Entity\\ProductEntity
    range of c is App\\Entity\\CategoryEntity via p.categories
    retrieve (p) where p.price < :maxPrice
", [
    'maxPrice' => 50.00
]);
```

## Core Components

ObjectQuel consists of several primary components working together:

- **EntityManager**: Central wrapper around the various helper classes
- **EntityStore**: Manages entity classes and their relationships
- **UnitOfWork**: Tracks individual entities and their changes
- **ObjectQuel**: Handles reading and parsing of the query language

## Configuration

### Creating a Configuration Object

```php
use Quellabs\ObjectQuel\Configuration;

// Create a new configuration object
$config = new Configuration();
```

### Setting Database Connection

You have multiple options for configuring the database connection:

#### Option 1: Using individual parameters

```php
$config->setDatabaseParams(
    'mysql',              // Database driver
    'localhost',          // Host
    'my_database',        // Database name
    'db_user',            // Username
    'db_password',        // Password
    3306,                 // Port (optional, default: 3306)
    'utf8mb4'             // Character set (optional, default: utf8mb4)
);
```

#### Option 2: Using a DSN string

```php
$config->setDsn('mysql://db_user:db_password@localhost:3306/my_database?encoding=utf8mb4');
```

#### Option 3: Using an array

```php
$config->setConnectionParams([
    'driver'   => 'mysql',
    'host'     => 'localhost',
    'database' => 'my_database',
    'username' => 'db_user',
    'password' => 'db_password',
    'port'     => 3306,
    'encoding' => 'utf8mb4'
]);
```

### Setting Entity Information

```php
// Set the base namespace for entities
// This is used when generating new entities through sculpt
$config->setEntityNamespace('App\\Entity');

// Set the entity path (directory where entities reside)
$config->setEntityPath(__DIR__ . '/src/Entity');
```

### Configuring Proxies for Lazy Loading

```php
// Set the directory where proxy classes will be stored
$config->setProxyDir(__DIR__ . '/var/cache/proxies');

// Set the namespace for generated proxy classes
$config->setProxyNamespace('App\\Proxies');
```

> **Important:** Without proper proxy configuration, proxies will be generated dynamically at runtime, significantly impacting performance.

### Configuring Metadata Caching

```php
// Enable metadata caching
$config->setUseMetadataCache(true);

// Set where metadata cache will be stored
$config->setMetadataCachePath(__DIR__ . '/var/cache/metadata');
```

### Creating the EntityManager

```php
use Quellabs\ObjectQuel\EntityManager;

// Create the EntityManager with your configuration
$entityManager = new EntityManager($config);
```

## Working with Entities

### Entity Retrieval

ObjectQuel provides three ways to retrieve entities:

#### 1. Using `find()`

```php
// Find entity by primary key
$entity = $entityManager->find(\App\Entity\ProductEntity::class, 23);
```

#### 2. Using `findBy()`

```php
// Find entities matching criteria
$entities = $entityManager->findBy(\App\Entity\ProductEntity::class, [
    'name' => 'Widget',
    'price' => 19.99
]);
```

#### 3. Writing a query

```php
// Complex query using ObjectQuel language
$results = $entityManager->executeQuery("
    range of p is App\\Entity\\ProductEntity
    retrieve (p) where p.productId = :productId
", [
    'productId' => 1525
]);

foreach($results as $row) {
    echo $row['p']->getName();
}
```

### Entity Creation

Entities are recognized by the `@Orm\Table` annotation:

```php
/**
 * Class ProductEntity
 * @Orm\Table(name="products")
 */
class ProductEntity {
    /**
     * @Orm\Column(name="product_id", type="int", length=11, primary_key=true)
     * @Orm\PrimaryKeyStrategy(strategy="auto_increment")
     */
    private int $productId;

    // Properties and methods...
}
```

#### Column Annotation Properties

Each database/entity property is marked by an @Orm\Column annotation. This annotation supports the following parameters:

| Parameter | Description | Options/Format |
|-----------|-------------|----------------|
| **name** | The database column name | Required |
| **type** | The data type | 'smallint', 'integer', 'float', 'string', 'text', 'guid', 'date', 'datetime' |
| **length** | The column length | Only relevant for string types |
| **primary_key** | Define this as a primary key column | true or false |
| **default** | Default value when database column is NULL | Value |
| **unsigned** | For unsigned values | true (unsigned) or false (signed, default) |
| **nullable** | Allow NULL values in the database | true (allow NULL) or false (non-NULL required, default) |

#### Primary Key Strategies

For primary key properties, you can apply the @Orm\PrimaryKeyStrategy annotation to define how key values are generated. ObjectQuel supports the following strategies:

| Strategy | Description |
|----------|-------------|
| **auto_increment** | Automatically increments values (default strategy) |
| **uuid** | Generates a unique UUID for each new record |
| **sequence** | Uses a select query to determine the next value in the sequence |

## The ObjectQuel Language

ObjectQuel draws inspiration from QUEL, a pioneering database query language developed in the 1970s for the Ingres DBMS at UC Berkeley (later acquired by Oracle). While SQL became the industry standard, QUEL's elegant approach to querying has been adapted here for modern entity-based programming:

- **Entity-Centric**: Works with domain entities instead of database tables
- **Intuitive Syntax**: Uses `RETRIEVE` instead of `SELECT` for more natural data extraction
- **Semantic Aliasing**: Defines aliases with `range of x is y` (similar to `FROM y AS x` in SQL) to create a more readable data scope
- **Object-Oriented**: References entity properties directly instead of database columns, maintaining your domain language
- **Relationship Traversal**: Simplifies complex data relationships through intuitive path expressions

While ObjectQuel ultimately translates to SQL, implementing our own query language provides significant advantages. The abstraction layer allows ObjectQuel to:

1. Express complex operations with elegant, developer-friendly syntax (e.g., `productId = /^a/` instead of SQL's more verbose `productId REGEXP('^a')`)
2. Intelligently optimize database interactions by splitting operations into multiple efficient SQL queries when needed
3. Perform additional post-processing operations not possible in SQL alone, such as seamlessly joining traditional database data with external JSON sources

This approach delivers both a more intuitive developer experience and capabilities that extend beyond standard SQL, all while maintaining a consistent, object-oriented interface.

### Entity Property Selection

ObjectQuel provides flexibility in what data you retrieve. You can:

#### Retrieve entire entity objects:

```php
$results = $entityManager->executeQuery("
    range of p is App\\Entity\\ProductEntity
    retrieve (p) where p.productId = :productId
", [
    'productId' => 1525
]);

// Access the retrieved entity
$product = $results[0]['p'];
```

#### Retrieve specific properties:

```php
// Returns only the price property values
$results = $entityManager->executeQuery("
    range of p is App\\Entity\\ProductEntity
    retrieve (p.price) where p.productId = :productId
", [
    'productId' => 1525
]);

// Access the retrieved property value
$price = $results[0]['p.price'];
```

#### Retrieve a mix of entities and properties:

```php
// Returns product entities and just the name property from descriptions
$results = $entityManager->executeQuery("
    range of p is App\\Entity\\ProductEntity
    range of d is App\\Entity\\ProductDescriptionEntity via p.descriptions
    retrieve (p, d.productName) where p.productId = :productId
    sort by d.productName asc
", [
    'productId' => 1525
]);

// Access the mixed results
$product = $results[0]['p'];
$name = $results[0]['d.productName'];
```

### Search Operations

ObjectQuel transforms database querying with its expressive, developer-friendly syntax that converts complex search operations into elegant, readable code.

| Operation | Example | Description |
|-----------|---------|-------------|
| Exact match | `main.name = "xyz"` | Exact value match |
| Starts with | `main.name = "xyz*"` | Starts with "xyz" |
| Pattern | `main.name = "abc*xyz"` | Starts with "abc", ends with "xyz" |
| Wildcard | `main.name = "h?nk"` | Single character wildcard |
| Regex | `main.name = /^a/` | Regular expression support |
| Full-text | `search(main.name, "banana cherry +pear -apple")` | Full-text search with weights |

### Pagination

ObjectQuel supports pagination with the WINDOW operator:

```php
range of p is App\\Entity\\ProductEntity
range of d is App\\Entity\\ProductDescriptionEntity via d.productId = p.productId
retrieve (p.productId) sort by d.productName
window 1 using window_size 10
```

## Entity Relationships

ObjectQuel supports four types of relationships:

### 1. OneToOne (owning-side)

```php
/**
 * @Orm\OneToOne(targetEntity="CustomerEntity", inversedBy="customerId", relationColumn="customerInfoId", fetch="EAGER")
 */
private ?CustomerEntity $customer;
```

| Parameter | Description                                           |
|-----------|-------------------------------------------------------|
| targetEntity | Target entity class                                   |
| inversedBy | Property in target entity for reverse mapping         |
| relationColumn | Column storing the foreign key                        |
| fetch | Loading strategy ("EAGER" or "LAZY"; LAZY is default) |

### 2. OneToOne (inverse-side)

```php
/**
 * @Orm\OneToOne(targetEntity="CustomerEntity", mappedBy="customerId", relationColumn="customerId")
 */
private ?CustomerEntity $customer;
```

| Parameter | Description |
|-----------|-------------|
| targetEntity | Target entity class |
| mappedBy | Property in target entity that holds the foreign key |
| relationColumn | Column in current entity that corresponds to the relationship |

### 3. ManyToOne (owning-side)

```php
/**
 * @Orm\ManyToOne(targetEntity="CustomerEntity", inversedBy="customerId", fetch="EAGER")
 * @Orm\RequiredRelation
 */
private ?CustomerEntity $customer;
```

| Parameter | Description |
|-----------|-------------|
| targetEntity | Target entity class |
| inversedBy | Property in target entity for reverse collection mapping |
| fetch | Loading strategy ("EAGER" or "LAZY", optional) |
| @Orm\RequiredRelation | Indicates that the relation can be loaded using an INNER JOIN (rather than the default LEFT JOIN) because it's guaranteed to be present, which improves query performance when the related entity must exist |

### 4. OneToMany (inverse-side)

```php
/**
 * @Orm\OneToMany(targetEntity="AddressEntity", mappedBy="customerId")
 * @var $addresses EntityCollection
 */
public $addresses;
```

| Parameter | Description |
|-----------|-------------|
| targetEntity | Target entity class |
| mappedBy | Property in target entity that contains the foreign key |
| fetch | Loading strategy ("EAGER" or "LAZY") |
| indexBy | Optional property to use as collection index |

### 5. ManyToMany

ManyToMany relationships are implemented as a specialized extension of OneToMany/ManyToOne relationships. To establish an effective ManyToMany relation:

1. Apply the `@EntityBridge` annotation to your entity class that will serve as the junction table.
2. This annotation instructs the query processor to treat the entity as an intermediary linking table.
3. When queries execute, the processor automatically traverses and loads the related ManyToOne associations defined within this bridge entity.

```php
/**
 * Class ProductCategoryEntity
 * @Orm\Table(name="products_categories")
 * @Orm\EntityBridge
 */
class ProductCategoryEntity {
    // Properties defining the relationship
}
```
The `@Orm\EntityBridge` pattern extends beyond basic relationship mapping by offering several advanced capabilities:
- Store supplementary data within the junction table (relationship metadata, timestamps, etc.)
- Access and manipulate this contextual data alongside the primary relationship information
- Maintain comprehensive audit trails and relationship history between associated entities

## Saving and Persisting Data

### Updating an Entity

```php
// Retrieve an existing entity by its primary key
// This queries the database for a ProductsAttributesEntity with ID 10
// The entity is immediately loaded and tracked by the EntityManager
// IMPORTANT: If no entity with ID 10 exists, this will return NULL, so error handling may be needed
$entity = $entityManager->find(ProductEntity::class, 10);

// Update entity property value
// Modifies the text property/field of the retrieved entity
// The EntityManager automatically tracks this change since the entity was loaded via find()
$entity->setText("Updated description");

// Register the entity with the EntityManager's identity map
// NOTE: This call is optional in this case because entities retrieved via find() are automatically
// tracked by the EntityManager. Including it may improve code readability by explicitly showing
// which entities are being managed, especially in complex operations
$entityManager->persist($entity);

// Synchronize all pending changes with the database
// This operation:
// 1. Detects changes to managed entities (the text field modification in this case)
// 2. Executes necessary SQL statements (UPDATE in this case)
// 3. Commits the transaction
// 4. Clears the identity map (tracking information)
// After flush(), the EntityManager no longer knows about previously managed entities
$entityManager->flush();
```

### Adding a New Entity

```php
// Create a new entity instance
// This instantiates a fresh entity object in memory without persisting it to the database yet
$entity = new ProductEntity();

// Set entity property value
// At this point, the change exists only in memory
$entity->setText("New product description");

// Register the entity with the EntityManager's identity map
// This tells the EntityManager to start tracking changes for this entity
// IMPORTANT: This step is mandatory in ObjectQuel, unlike some other ORMs that automatically track new entities
// Without this call, the entity would not be saved to the database during flush operations
$entityManager->persist($entity);

// Synchronize all pending changes with the database
// This operation:
// 1. Executes all necessary SQL statements (INSERT in this case)
// 2. Commits the transaction
// 3. Clears the identity map (tracking information)
// After flush(), the EntityManager no longer knows about previously managed entities
$entityManager->flush();
```

### Removing an Entity

```php
// Retrieve an existing entity by its primary key
// This queries the database for a SpecialsEntity with ID 1520
// The entity is immediately loaded and tracked by the EntityManager
// If no entity with ID 1520 exists, this will return NULL, so error handling may be needed
$entity = $entityManager->find(ProductEntity::class, 1520);

// Mark the entity for removal
// This schedules the entity for deletion when flush() is called
// The entity is not immediately deleted from the database
// NOTE: This only works for entities that are being tracked by the EntityManager
$entityManager->remove($entity);

// Synchronize all pending changes with the database
// This operation:
// 1. Executes necessary SQL statements (DELETE in this case)
// 2. Commits the transaction
// 3. Clears the identity map (tracking information)
// After flush(), the EntityManager no longer tracks this entity, and the record is removed from the database
$entityManager->flush();
```

## Using Repositories

ObjectQuel provides a flexible approach to the Repository pattern through its optional `Repository` base class. Unlike some ORMs that mandate repository usage, ObjectQuel makes repositories entirely optional—giving you the freedom to organize your data access layer as you prefer.

### Repository Pattern Benefits

The Repository pattern creates an abstraction layer between your domain logic and data access code, providing several advantages:

- **Type Safety**: Better IDE autocomplete and type hinting
- **Code Organization**: Centralizes query logic for specific entity types
- **Business Logic**: Encapsulates common data access operations
- **Testability**: Simplifies mocking for unit tests
- **Query Reusability**: Prevents duplication of common queries

### Creating Custom Repositories

While you can work directly with the EntityManager, creating entity-specific repositories can enhance your application's structure:

```php
use Quellabs\ObjectQuel\Repository;

class ProductRepository extends Repository {
    
    /**
     * Constructor - specify the entity this repository manages
     * @param EntityManager $entityManager The EntityManager instance
     */
    public function __construct(EntityManager $entityManager) {
        parent::__construct($entityManager, ProductEntity::class);
    }
        
    /**
     * Find products below a certain price
     * @param float $maxPrice Maximum price threshold
     * @return array<ProductEntity> Matching products
     */
    public function findBelowPrice(float $maxPrice): array {
        return $this->entityManager->executeQuery("
            range of p is App\\Entity\\ProductEntity
            retrieve (p) where p.price < :maxPrice
            sort by p.price asc
        ", [
            'maxPrice' => $maxPrice
        ]);
    }
}
```

### Using Repositories in Your Application
Once you've defined your repositories, you can integrate them into your application:

```php
// Create the repository
$productRepository = new ProductRepository($entityManager);

// Use repository methods
$affordableProducts = $productRepository->findBelowPrice(29.99);

// Still have access to built-in methods
$specificProduct = $productRepository->find(1001);
$featuredProducts = $productRepository->findBy(['featured' => true]);
```

## Utility Tools

ObjectQuel provides a powerful utility tool called `sculpt` that streamlines
the creation of entities in your application. This interactive CLI tool guides you
through a structured process, automatically generating properly formatted entity classes
with all the necessary components.

### Initialization

Before using the `sculpt` tool, create an `objectquel-cli-config.php` configuration file in your project's root directory (where your `composer.json` file is located). This file must include your database credentials, entity namespace, entity path, and migration path.

For convenience, ObjectQuel provides an `objectquel-cli-config.php.example` file that you can copy and customize with your specific settings. The CLI tools require this configuration file to function properly.

### Automatic Entity Generation

To create a new entity, run the following command in your terminal:

```bash
php bin/sculpt make:entity
```

When you execute this command, the `sculpt` tool will:

1. **Prompt for entity name** - Enter a descriptive name for your entity (e.g., "User", "Product", "Order")
2. **Define properties** - Add fields with their respective data types (string, integer, boolean, etc.)
3. **Establish relationships** - Define connections to other entities (One-to-One, One-to-Many, etc.)
4. **Generate accessors** - Create getters and setters for your properties

### Creating Entities from Database Tables

To generate an entity from an existing database table, run this command in your terminal:

```bash
php bin/sculpt make:entity-from-table
```

When executed, the sculpt tool will prompt you to select a table name and automatically create a properly structured entity class based on that table's schema.

### Generating Database Migrations

To create migrations for entity changes, use this command:

```bash
php bin/sculpt make:migrations
```

When executed, the sculpt tool analyzes differences between your entity definitions and the current database schema. It then automatically generates a migration file containing the necessary SQL statements to synchronize your database with your entities.

**Note:** The system uses CakePHP's Phinx as its migration engine. All generated migrations follow the Phinx format and can be executed using standard Phinx commands.

## Query Optimization

### Query Flags

ObjectQuel supports query flags for optimization, starting with the '@' symbol:

- `@InValuesAreFinal`: Optimizes IN() functions for primary keys by eliminating verification queries

## Important Notes

- Proxy cache directories must be writable by the application
- For best performance in production, enable proxy and metadata caching

> When proxy path and namespace settings are not configured, the system generates proxies on-the-fly during runtime. This approach significantly reduces performance and can cause noticeable slowdowns in your application. For optimal performance, always configure both the proxy path and namespace in your application settings.

## License

ObjectQuel is released under the MIT License.

```
MIT License

Copyright (c) 2024-2025 ObjectQuel

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```