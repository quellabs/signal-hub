# ObjectQuel

ObjectQuel is a sophisticated Object-Relational Mapping (ORM) system that brings a fresh approach to database interaction. Drawing inspiration from Symfony's Doctrine, ObjectQuel establishes its own identity with a unique query language and streamlined architecture.

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
// Enable proxy generation (required for lazy loading)
$config->setUseProxies(true);

// Set where proxy classes will be stored
$config->setProxyDir(__DIR__ . '/var/cache/proxies');

// Set the namespace for proxy classes
$config->setProxyNamespace('Quellabs\\ObjectQuel\\Proxies');

// Enable automatic generation of proxy classes
$config->setAutoGenerateProxyClasses(true);
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
use Quellabs\ObjectQuel\EntityManager;

// Create the EntityManager with your configuration
$entityManager = new EntityManager($config);
```

## Entity Retrieval

ObjectQuel provides three ways to retrieve entities:

### 1. Using `find()`

The simplest method to retrieve an entity by its primary key:

```php
$entityManager = $stApp->getContainer(\Services\EntityManager\EntityManager::class);
$entity = $entityManager->find(\Services\Entity\CustomersInfoEntity::class, 23);
```

### 2. Using `findBy()`

Retrieve entities matching specific criteria:

```php
$entityManager = $stApp->getContainer(\Services\EntityManager\EntityManager::class);
$entities = $entityManager->findBy(\Services\Entity\CustomersInfoEntity::class, ['firstName' => 'Henk']);
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

$results = $rs->fetchResults();
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
    sort by x.productsName asc, x.ietsAnders desc
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
     */
    private int $customersInfoId;

    // Properties and methods...
}
```

### Entity Relationships

ObjectQuel supports four types of relationships:

1. **OneToOne**: Direct relation between two entities where each entity can be associated with only one instance of the other entity.
2. **OneToMany**: One entity linked to multiple entities, where the "one" side can have multiple references to entities on the "many" side.
3. **ManyToOne**: Multiple entities linked to a single entity, effectively the inverse perspective of OneToMany.
4. **ManyToMany**: Multiple entities linked to multiple entities, implemented through combination of OneToMany/ManyToOne patterns with a join table.

#### OneToOne example:

```php
// OneToOne (owning-side)
/**
 * @Orm\OneToOne(targetEntity="CustomersEntity", inversedBy="customersId", relationColumn="customersInfoId", fetch="EAGER")
 */
private ?CustomersEntity $parent;
```

#### OneToMany example:

```php
// OneToMany (not owning side)
/**
 * @Orm\OneToMany(targetEntity="AddressBookEntity", mappedBy="customersId", fetch="EAGER")
 * @var $addressBooks EntityCollection
 */
public $addressBooks;
```

#### ManyToOne example:
```php

// ManyToOne (owning-side)
/**
 * @Orm\ManyToOne(targetEntity="CustomersEntity", inversedBy="customersId")
 * @Orm\RequiredRelation
 */
private ?CustomersEntity $customer;
```

### ManyToMany

ManyToMany relationships are implemented as a specialized extension of OneToMany/ManyToOne relationships. To establish an effective ManyToMany relation:

1. Apply the `@EntityBridge` annotation to your entity class that will serve as the junction table.
2. This annotation instructs the query processor to treat the entity as an intermediary linking table.
3. When queries execute, the processor automatically traverses and loads the related ManyToOne associations defined within this bridge entity.

This architecture leverages the existing OneToMany/ManyToOne infrastructure while providing transparent access to related entities through the bridge, significantly simplifying complex relationship management.

The `@EntityBridge` pattern extends beyond basic relationship mapping by offering several advanced capabilities:
- Store supplementary data within the junction table (relationship metadata, timestamps, or configuration parameters)
- Access and manipulate this contextual data alongside the primary relationship information
- Maintain comprehensive audit trails and relationship history between associated entities

This approach combines the performance benefits of traditional relational database design with the flexibility and expressiveness needed for complex domain modeling.

### Saving and Persisting Data

ObjectQuel uses `persist()` and `flush()` for saving data:

```php
// Update an existing entity
$entity = $entityManager->find(ProductsAttributesEntity::class, $attributeId);
$entity->setText("hello");
$entityManager->persist($entity); // Optional but provides clarity
$entityManager->flush();

// Create a new entity
$product = $entityManager->find(ProductsEntity::class, 1520);
$entity = new ProductsAttributesEntity();
$entity->setProduct($product);
$entity->setText("hello");
$entityManager->persist($entity); // Required for new entities
$entityManager->flush();
```

### Deleting Entities

```php
$entity = $entityManager->find(SpecialsEntity::class, 1520);
$entityManager->remove($entity);
$entityManager->flush();
```

## Utility Tools

### Automatic Entity Generation

ObjectQuel provides a powerful utility tool called `sculpt` that streamlines
the creation of entities in your application. This interactive CLI tool guides you
through a structured process, automatically generating properly formatted entity classes
with all the necessary components.

To create a new entity, run the following command in your terminal:

```php
php bin/sculpt make:entity
```

When you execute this command, the `sculpt` tool will:

1. **Prompt for entity name** - Enter a descriptive name for your entity (e.g., "User", "Product", "Order")
2. **Define properties** - Add fields with their respective data types (string, integer, boolean, etc.)
3. **Establish relationships** - Define connections to other entities (One-to-One, One-to-Many, etc.)
4. **Generate accessors** - Create getters and setters for your properties

## Query Optimization

### Query Flags

ObjectQuel supports query flags for optimization, starting with the '@' symbol:

- `@InValuesAreFinal`: Optimizes IN() functions for primary keys by eliminating additional verification queries

## Important Notes

- Proxy cache directories must be writable by the application
- Proxy generation is required for lazy loading features to work
- For the best performance in production, enable metadata caching

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