# ObjectQuel

ObjectQuel is a powerful Object-Relational Mapping (ORM) system that revolutionizes database
interaction through its data mapper pattern implementation, intuitive query language, and
efficient architecture. Built on CakePhp's robust database foundation (https://book.cakephp.org/4/en/orm/database-basics.html), it 
delivers a distinctive approach to data management that separates your domain objects from persistence concerns
while maintaining developer-friendly simplicity. 

## Key Features

- **Data Mapper Pattern**: Maintains clear separation between domain models and underlying database structures
- **Intuitive Entity Relationship Management**: Simplifies working with related data objects
- **Object-Oriented Query Language**: Focus on entities rather than tables for more natural database operations
- **Hybrid Data Source Integration**: Seamlessly combine traditional database entities with external data sources (currently supporting JSON)
- **Optimized Query Performance**: Multiple optimization strategies for efficient database interactions

## Core Components

ObjectQuel consists of an EntityManager with several helper classes:

- **EntityManager**: Central wrapper around the various helper classes
- **EntityStore**: Manages entity classes and their relationships
- **UnitOfWork**: Tracks individual entities and their changes
- **ObjectQuel**: Handles reading and parsing of the query language

## Configuration System

The configuration system centralizes all settings related to your ORM, including:

- Database connection parameters
- Entity class locations and namespace
- Proxy configuration for lazy loading
- Metadata caching options

### Creating a Configuration Object

```php
use Quellabs\ObjectQuel\Configuration;

// Create a new configuration object
$config = new Configuration();
```

### Setting Database Connection

You have multiple options for configuring the database connection:

**Option 1: Using individual parameters**

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

**Option 2: Using a DSN string**

```php
$config->setDsn('mysql://db_user:db_password@localhost:3306/my_database?encoding=utf8mb4');
```

**Option 3: Using an array**

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

Configure where your entities are located:

```php
// Set the base namespace for entities
// This is used when generating new entities through sculpt
$config->setEntityNamespace('Quellabs\\ObjectQuel\\Entity');

// Set the entity path (directory where entities reside)
$config->setEntityPath(__DIR__ . '/src');
```

### Configuring Proxies for Lazy Loading

ObjectQuel uses proxy classes to implement lazy loading. Configure how these proxies are managed:

```php
// Set the directory where proxy classes will be stored
// This is highly recommended as it significantly improves performance
// Without this setting, proxies will be generated dynamically at runtime
$config->setProxyDir(__DIR__ . '/var/cache/proxies');

// Set the namespace for generated proxy classes
// This namespace should be unique to avoid conflicts with other code
$config->setProxyNamespace('Quellabs\\ObjectQuel\\Proxies');
```

### Configuring Metadata Caching

For better performance, ObjectQuel can cache entity metadata:

```php
// Enable metadata caching
$config->setUseMetadataCache(true);

// Set where metadata cache will be stored
$config->setCacheDir(__DIR__ . '/var/cache/metadata');
```

### Creating the EntityManager

Once you've configured all settings, create the EntityManager:

```php
use Quellabs\ObjectQuel;

// Create the EntityManager with your configuration
$entityManager = new EntityManager($config);
```

## Entity Retrieval

ObjectQuel provides three ways to retrieve entities:

### 1. Using `find()`

The simplest method to retrieve an entity by its primary key:

```php
$entity = $entityManager->find(\Services\Entity\CustomersInfoEntity::class, 23);
```

### 2. Using `findBy()`

Retrieve entities matching specific criteria:

```php
$entities = $entityManager->findBy(\Services\Entity\CustomersInfoEntity::class, [
    'firstName' => 'John',
    'lastName'  => 'Doe'
]);
```

### 3. Writing a query

For more complex queries, use the ObjectQuel language:

```php
$rs = $entityManager->executeQuery("
    range of main is Services\Entity\ProductsEntity
    retrieve (main) where main.productsId=:productsId
", [
    'productsId' => 1525
]);

foreach($rs as $row) {
    echo $row['main']->getProductsId();
}
```

## The ObjectQuel Language

ObjectQuel is a variant of the Oracle/Ingres language Quel, adapted for handling entities:

- Works with entities instead of tables
- Uses `RETRIEVE` instead of `SELECT`
- Defines aliases with `range of x is y` (similar to `FROM y AS x` in SQL)
- References entity properties instead of database columns

Example:
```php
$rs = $entityManager->executeQuery("
    range of main is Services\Entity\ProductsEntity
    range of x is Services\Entity\ProductsDescriptionEntity via main.productsDescriptions
    retrieve (x.productsName) where main.productsId=:productsId
    sort by x.productsName asc, x.somethingElse desc
", [
    'productsId' => 1525
]);
```

### Search Operations

ObjectQuel provides powerful search operations:

```php
// Exact match
retrieve (main.productsName) where main.productsName = "xyz"

// Pattern matching (starts with xyz)
retrieve (main.productsName) where main.productsName = "xyz*"

// Pattern matching (starts with abc and ends with xyz)
retrieve (main.productsName) where main.productsName = "abc*xyz"

// Single character wildcard
retrieve (main.productsName) where main.productsName = "h?nk"

// Regular expression support
retrieve (main.productsName) where main.productsName = /^a/

// Full-text search
retrieve(main) where search(main.productsName, "banana cherry +pear -apple")
```

### Pagination

ObjectQuel supports pagination with the WINDOW operator:

```php
range of x is ProductsEntity
range of y is ProductsDescriptionEntity via y.productsId=x.productsId
retrieve (x.productsId) sort by y.productsName
window 1 using window_size 10
```

### Using ObjectQuel Query Language

You can use the powerful ObjectQuel query language:

```php
$query = "
    range of p is Quellabs\\ObjectQuel\\Entity\\ProductEntity
    range of c is Quellabs\\ObjectQuel\\Entity\\CategoryEntity via p.categories
    retrieve (p, c) where p.price > :minPrice AND c.name = :categoryName
";

$parameters = [
    'minPrice'     => 50.00,
    'categoryName' => 'Electronics'
];

$result = $entityManager->executeQuery($query, $parameters);
$products = $result->fetchResults();
```

## Entity Creation and Management

### Creating Entities

Entities are recognized by the `@Orm\Table` annotation:

```php
/**
 * Class CustomersInfoEntity
 * @package Services\Entity
 * @Orm\Table(name="customers_info")
 */
class CustomersInfoEntity {
    /**
     * @Orm\Column(name="customers_info_id", type="int", length=11, primary_key=true)
     * @Orm\PrimaryKeyStrategy(strategy="auto_increment")
     */
    private int $customersInfoId;

    // Properties and methods...
}
```

Each database/entity property is marked by an @Orm\Column annotation. This annotation supports the following parameters:

1. **name**: The database column name (required)
2. **type**: The data type - options include 'smallint', 'integer', 'float', 'string', 'text', 'guid', 'date', or 'datetime'
3. **length**: The column length (only relevant for string types)
4. **primary_key**: Set to true to define this as a primary key column, false otherwise
5. **default**: Specifies the default value when the database column is NULL
6. **unsigned**: Set to true for unsigned values, false for signed values (signed is default)
7. **nullable**: Set to true to allow NULL values in the database, false to require non-NULL values (not nullable is default).

For primary key properties, you can apply the @Orm\PrimaryKeyStrategy annotation to define how key values are generated. ObjectQuel supports the following strategies:

1. **auto_increment**: Automatically increments values (default strategy)
2. **uuid**: Generates a unique UUID for each new record
3. **sequence**: Uses a select query to determine the next value in the sequence

**Note:** While you can define multiple columns as primary keys, ObjectQuel will only use the first one specified.

### Entity Relationships

ObjectQuel supports four types of relationships:

1. **OneToOne**: Direct relation between two entities where each entity can be associated with only one instance of the other entity.
2. **OneToMany**: One entity linked to multiple entities, where the "one" side can have multiple references to entities on the "many" side.
3. **ManyToOne**: Multiple entities linked to a single entity, effectively the inverse perspective of OneToMany.
4. **ManyToMany**: Multiple entities linked to multiple entities, implemented through combination of OneToMany/ManyToOne patterns with a join table.

#### OneToOne (owning-side) example:

```php
/**
 * @Orm\OneToOne(targetEntity="CustomersEntity", inversedBy="customersId", relationColumn="customersInfoId", fetch="EAGER")
 */
private ?CustomersEntity $parent;
```

The annotation has the following elements:

1. **targetEntity:** Specifies the target entity class ("CustomersEntity") that this relationship maps to - this defines which entity is on the other side of the relationship
2. **inversedBy:** Indicates the property name in the target entity ("customersId") that contains the reverse mapping back to this entity - creates bidirectional navigation
3. **relationColumn:** Specifies the column name in the current entity ("customersInfoId") that stores the foreign key for this relationship
4. **fetch="EAGER":** Configures the loading strategy to load the related entity immediately whenever the current entity is loaded, rather than loading it lazily on demand

This OneToOne relationship establishes a direct one-to-one connection between the current entity and a CustomersEntity instance, with the foreign key stored in the customersInfoId column.

#### OneToOne (inverse-side) example:

```php
/**
 * @Orm\OneToOne(targetEntity="CustomersEntity", mappedBy="customersId", relationColumn="customersId", fetch="LAZY")
 */
private ?CustomersEntity $parent;
```

The annotations have the following elements:

1. **targetEntity:** Specifies the target entity class ("CustomersEntity") that this relationship maps to - this defines which entity is on the other side of the relationship
2. **mappedBy:** Indicates the property name in the target entity ("customersId") that contains the foreign key - this marks the current entity as the "inverse" side of the relationship
3. **relationColumn:** Specifies the column name in the current entity ("customersId") that establishes the relationship connection point, even though the foreign key is stored in the target entity
4. **fetch="LAZY":** Configures the loading strategy to load the related entity only when it's actually accessed (on-demand loading), rather than loading it immediately with the parent entity - this can improve performance by avoiding unnecessary data loading

This OneToOne relationship establishes a direct one-to-one connection between the current entity and a CustomersEntity instance. As the "inverse" side (specified by mappedBy), the current entity doesn't store the foreign key - instead, the CustomersEntity holds the foreign key. The relationColumn specifies which property in the current entity corresponds to this relationship, providing an explicit connection point for the ORM to use when traversing the relationship.

#### ManyToOne (owning-side) example :
```php
/**
 * @Orm\ManyToOne(targetEntity="CustomersEntity", inversedBy="customersId")
 * @Orm\RequiredRelation
 */
private ?CustomersEntity $customer;
```

The annotations have the following elements:

1. **targetEntity:** Specifies the target entity class ("CustomersEntity") that this relationship maps to - this defines which entity is on the other side of the relationship
2. **inversedBy:** Indicates the property name in the target entity ("customersId") that contains the reverse mapping (collection) back to this entity - enables bidirectional navigation
3. **@Orm\RequiredRelation:** Indicates that the relation can be loaded using an INNER JOIN (rather than the default LEFT JOIN) because it's guaranteed to be present, which improves query performance when the related entity must exist

This ManyToOne relationship establishes that multiple instances of the current entity can reference a single CustomersEntity instance, with the current entity being the "owning" side that contains the foreign key. The nullable PHP type declaration (?CustomersEntity) indicates that while the relation is required when present, the property itself can be null.

#### OneToMany (inverse-side) example:

```php
/**
 * @Orm\OneToMany(targetEntity="AddressBookEntity", mappedBy="customersId", fetch="EAGER")
 * @var $addressBooks EntityCollection
 */
public $addressBooks;
```

1. **targetEntity:** Specifies the target entity class ("AddressBookEntity") that this relationship maps to - this defines which entity is on the other side of the relationship
2. **mappedBy:** Indicates the property name in the target entity ("customersId") that contains the foreign key to this entity - this marks the current entity as the "inverse" side of the relationship
3. **fetch="EAGER"**: Configures the loading strategy to load all related entities immediately whenever the current entity is loaded, rather than loading them lazily on demand

This OneToMany relationship establishes that a single instance of the current entity can be associated with multiple AddressBookEntity instances. The current entity is the "inverse" side, meaning the foreign key is stored in the AddressBookEntity table rather than in the current entity's table. The EntityCollection provides specialized collection functionality for managing the related entities.

#### ManyToMany

ManyToMany relationships are implemented as a specialized extension of OneToMany/ManyToOne relationships. To establish an effective ManyToMany relation:

```php
/**
 * Class ProductsDescriptionEntity
 * @Orm\Table(name="products_description")
 * @Orm\EntityBridge
 */
class ProductsDescriptionEntity {
```

1. Apply the `@EntityBridge` annotation to your entity class that will serve as the junction table.
2. This annotation instructs the query processor to treat the entity as an intermediary linking table.
3. When queries execute, the processor automatically traverses and loads the related ManyToOne associations defined within this bridge entity.

The `@EntityBridge` pattern extends beyond basic relationship mapping by offering several advanced capabilities:
- Store supplementary data within the junction table (relationship metadata, timestamps, etc.)
- Access and manipulate this contextual data alongside the primary relationship information
- Maintain comprehensive audit trails and relationship history between associated entities

This approach combines the performance benefits of traditional relational database design with the flexibility and expressiveness needed for complex domain modeling.

### Saving and Persisting Data

ObjectQuel uses `persist()` and `flush()` for saving data.

Example for updating an entity:

```php
// Retrieve an existing entity by its primary key
// This queries the database for a ProductsAttributesEntity with ID 10
// The entity is immediately loaded and tracked by the EntityManager
// IMPORTANT: If no entity with ID 10 exists, this will return NULL, so error handling may be needed
$entity = $entityManager->find(ProductsAttributesEntity::class, 10);

// Update entity property value
// Modifies the text property/field of the retrieved entity
// The EntityManager automatically tracks this change since the entity was loaded via find()
$entity->setText("hello");

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

Example for adding an entity:

```php
// Create a new entity instance
// This instantiates a fresh entity object in memory without persisting it to the database yet
$entity = new ProductsAttributesEntity();

// Set entity property value
// Assigns the value "hello" to the text property/field of the entity
// At this point, the change exists only in memory
$entity->setText("hello");

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

Example for removing an entity:

```php
// Retrieve an existing entity by its primary key
// This queries the database for a SpecialsEntity with ID 1520
// The entity is immediately loaded and tracked by the EntityManager
// If no entity with ID 1520 exists, this will return NULL, so error handling may be needed
$entity = $entityManager->find(SpecialsEntity::class, 1520);

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

```php
php bin/sculpt make:entity
```

When you execute this command, the `sculpt` tool will:

1. **Prompt for entity name** - Enter a descriptive name for your entity (e.g., "User", "Product", "Order")
2. **Define properties** - Add fields with their respective data types (string, integer, boolean, etc.)
3. **Establish relationships** - Define connections to other entities (One-to-One, One-to-Many, etc.)
4. **Generate accessors** - Create getters and setters for your properties

### Creating Entities from Existing Database Tables

To generate an entity from an existing database table, run this command in your terminal:

```php
php bin/sculpt make:entity-from-table
```

When executed, the sculpt tool will prompt you to select a table name and automatically create a properly structured entity class based on that table's schema.

**Note:** Currently, this command does not automatically create relationships between entities. To add relationships after generating your entity, use the make:entity command to modify your newly created entity.

### Generating Database Migrations
To create migrations for entity changes, use this command:

```php
php bin/sculpt make:migrations
```

When executed, the sculpt tool analyzes differences between your entity definitions and the current database schema. It then automatically generates a migration file containing the necessary SQL statements to synchronize your database with your entities.

**Note:** The system uses CakePHP's Phinx as its migration engine. All generated migrations follow the Phinx format and can be executed using standard Phinx commands.

## Query Optimization

### Query Flags

ObjectQuel supports query flags for optimization, starting with the '@' symbol:

- `@InValuesAreFinal`: Optimizes IN() functions for primary keys by eliminating additional verification queries

## Important Notes

- Proxy cache directories must be writable by the application
- For the best performance in production, enable proxy and metadata caching **

** When proxy path and namespace settings are not configured, the system generates proxies on-the-fly during runtime. This approach significantly reduces performance and can cause noticeable slowdowns in your application. For optimal performance, always configure both the proxy path and namespace in your application settings.

## Contributing

[Guidelines for contributing to the project would go here]

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