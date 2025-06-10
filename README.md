# PHP Annotation Reader

[![Latest Version](https://img.shields.io/packagist/v/quellabs/annotation-reader.svg)](https://packagist.org/packages/quellabs/signal-hub)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/quellabs/annotation-reader.svg)](https://packagist.org/packages/quellabs/signal-hub)

A powerful PHP annotation reader for parsing, processing, and caching docblock annotations in PHP classes.

## Overview

The AnnotationReader component provides robust parsing and caching of PHP docblock annotations, allowing you to define metadata directly within your class docblocks. This approach makes your code more self-documenting and reduces the need for separate configuration files.

## Features

- **Annotation parsing**: Parse docblock annotations for classes, properties, and methods
- **Annotation inheritance**: Class-level annotations are automatically inherited by child classes
- **Import resolution**: Automatically resolves class imports for fully qualified annotation names
- **Performance optimization**: Implements smart caching to improve performance
- **Flexible integration**: Easy to integrate with your existing projects
- **Error handling**: Graceful handling of malformed annotations

## Installation

```bash
composer require quellabs/annotation-reader
```

## Usage

### Basic Usage

```php
use Quellabs\AnnotationReader\AnnotationsReader;
use Quellabs\AnnotationReader\Configuration;

// Create configuration
$config = new Configuration();
$config->setAnnotationCachePath(__DIR__ . '/cache');
$config->setUseAnnotationCache(true);

// Create annotation reader
$reader = new AnnotationsReader($config);

// Get annotations for a class
$classAnnotations = $reader->getClassAnnotations(MyClass::class);

// Get annotations for a class, filtered by a specific annotation
$classAnnotations = $reader->getClassAnnotations(MyClass::class, SomeAnnotation::class);

// Get annotations for a property
$propertyAnnotations = $reader->getPropertyAnnotations(MyClass::class, 'propertyName');

// Get annotations for a property, filtered by a specific annotation
$propertyAnnotations = $reader->getPropertyAnnotations(MyClass::class, 'propertyName', SomeAnnotation::class);

// Get annotations for a method
$methodAnnotations = $reader->getMethodAnnotations(MyClass::class, 'methodName');

// Get annotations for a method, filtered by a specific annotation
$methodAnnotations = $reader->getMethodAnnotations(MyClass::class, 'methodName', SomeAnnotation::class);
```

### Annotation Inheritance

Class-level annotations are automatically inherited from parent classes, making it easy to apply cross-cutting concerns like caching, security, or logging to entire class hierarchies.

```php
/**
 * @Cacheable(ttl=3600)
 * @Transactional
 */
abstract class BaseService {
    // Base service logic
}

/**
 * @RateLimited(requests=100, window=60)
 */
class UserService extends BaseService {
    // Inherits @Cacheable and @Transactional from BaseService
    // Also has its own @RateLimited annotation
}

// Get all class annotations (including inherited ones)
$annotations = $reader->getClassAnnotations(UserService::class);
// Returns: @Cacheable, @Transactional, @RateLimited

// Get only direct class annotations (no inheritance)
$directAnnotations = $reader->getClassAnnotations(UserService::class, null, false);
// Returns: @RateLimited only
```

#### Inheritance Behavior

- **Class annotations**: Inherited from parent classes by default
- **Method annotations**: Not inherited (direct only)
- **Property annotations**: Not inherited (direct only)
- **Inheritance order**: Parent annotations are processed first, child annotations can override
- **Optional inheritance**: Use the third parameter to disable inheritance: `getClassAnnotations($class, $filter, false)`

#### Common Use Cases for Inheritance

```php
/**
 * @Secured(role="ADMIN")
 * @Logged
 */
abstract class AdminController {
    // Base admin functionality
}

class UserManagementController extends AdminController {
    // Automatically secured and logged
}

class SystemConfigController extends AdminController {
    // Also automatically secured and logged
}
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

The AnnotationReader requires a Configuration object that specifies:

- Whether to use annotation caching
- The path to store annotation cache files

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.