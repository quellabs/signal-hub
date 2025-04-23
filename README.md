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
- **AnnotationReader**: Processes annotations for entity configuration
- **ObjectQuel**: Handles reading and parsing of the query language

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

### 3. Writing Custom ObjectQuel Queries

For more complex queries, use the ObjectQuel language:

```php
$rs = $entityManager->executeQuery("
    range of main is Services\Entity\ProductsEntity;
    retrieve (main) where main.productsId=:productsId", [
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
    range of main is Services\Entity\ProductsEntity;
    range of x is Services\Entity\ProductsDescriptionEntity via main.productsDescriptions
    retrieve (x.productsName) where main.productsId=:productsId
    sort by x.productsName asc, x.ietsAnders desc", [
    'productsId' => 1525
]);
```

### Search Operations

ObjectQuel provides powerful search operations:

```php
// Exact match
retrieve (main.productsName) where main.productsName = 'xyz'

// Pattern matching (starts with xyz)
retrieve (main.productsName) where main.productsName = "xyz*"

// Pattern matching (starts with abc and ends with xyz)
retrieve (main.productsName) where main.productsName = "abc*xyz"

// Single character wildcard
retrieve (main.productsName) where main.productsName = "h?nk"

// Regular expression support
retrieve (main.productsName) where main.productsName = `^a`

// Full text search
retrieve(main) where search(main.productsName, "banana cherry +pear -apple")
```

### Pagination

ObjectQuel supports pagination with the WINDOW operator:

```php
range of x is ProductsEntity
range of y is ProductsDescriptionEntity via y.productsId=x.productsId
retrieve (x.productsId) sort by y.productsName
window 1 using page_size 10
```

## Entity Creation and Management

### Creating Entities

Entities are placed in the `stApp/Services/Entity` folder and recognized by the `@Orm\Table` annotation:

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

ObjectQuel supports three types of relationships:

1. **OneToOne**: Direct relation between two entities
2. **OneToMany**: One entity linked to multiple entities
3. **ManyToOne**: Multiple entities linked to a single entity

Examples:

```php
// OneToOne (owning-side)
/**
 * @Orm\OneToOne(targetEntity="CustomersEntity", inversedBy="customersId", relationColumn="customersInfoId", fetch="EAGER")
 */
private ?CustomersEntity $parent;

// OneToMany (not owning side)
/**
 * @Orm\OneToMany(targetEntity="AddressBookEntity", mappedBy="customersId", fetch="EAGER")
 * @var $addressBooks EntityCollection
 */
public $addressBooks;

// ManyToOne (owning-side)
/**
 * @Orm\ManyToOne(targetEntity="CustomersEntity", inversedBy="customersId")
 * @Orm\RequiredRelation
 */
private ?CustomersEntity $customer;
```

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

ObjectQuel provides a utility script to automatically generate entities from existing database tables:

```bash
php create_entity.php <tablename>
```

Generated entities include all properties and getter/setter methods based on the table columns. Note that relationships must be manually added.

## Query Optimization

### Query Flags

ObjectQuel supports query flags for optimization, starting with the '@' symbol:

- `@InValuesAreFinal`: Optimizes IN() functions for primary keys by eliminating additional verification queries

## Contributing

[Guidelines for contributing to the project would go here]

## License

ObjectQuel is released under the MIT License.

```
MIT License

Copyright (c) 2025 ObjectQuel

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