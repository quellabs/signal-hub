# PHP Annotations Reader

A powerful PHP annotations reader for parsing, processing, and caching docblock annotations in PHP classes.

## Overview

The AnnotationsReader component provides robust parsing and caching of PHP docblock annotations, allowing you to define metadata directly within your class docblocks. This approach makes your code more self-documenting and reduces the need for separate configuration files.

## Features

- **Annotation parsing**: Parse docblock annotations for classes, properties, and methods
- **Import resolution**: Automatically resolves class imports for fully qualified annotation names
- **Performance optimization**: Implements smart caching to improve performance
- **Flexible integration**: Easy to integrate with your existing projects
- **Error handling**: Graceful handling of malformed annotations

## Installation

```bash
composer require quellabs/annotations-reader
```

## Usage

```php
use Quellabs\ObjectQuel\AnnotationsReader\AnnotationsReader;
use Quellabs\ObjectQuel\EntityManager\Configuration;

// Create configuration
$config = new Configuration();
$config->setAnnotationCachePath(__DIR__ . '/cache');
$config->setUseAnnotationCache(true);

// Create annotation reader
$reader = new AnnotationsReader($config);

// Get annotations for a class
$classAnnotations = $reader->getClassAnnotations(MyClass::class);

// Get annotations for a property
$propertyAnnotations = $reader->getPropertyAnnotations(MyClass::class, 'propertyName');

// Get annotations for a method
$methodAnnotations = $reader->getMethodAnnotations(MyClass::class, 'methodName');
```

## Annotation Format

Annotations are defined in PHP docblocks using the `@` symbol followed by the annotation name and optional parameters:

```php
/**
 * @Table(name="products")
 * @Entity
 */
class Product {
    /**
     * @Column(type="integer", primary=true, autoincrement=true)
     */
    private $id;
    
    /**
     * @Column(type="string", length=255)
     */
    private $name;
}
```

## Configuration

The AnnotationsReader requires a Configuration object that specifies:

- Whether to use annotation caching
- The path to store annotation cache files

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.