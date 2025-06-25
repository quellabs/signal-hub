# PHP Annotation Reader

[![Latest Version](https://img.shields.io/packagist/v/quellabs/annotation-reader.svg)](https://packagist.org/packages/quellabs/signal-hub)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/quellabs/annotation-reader.svg)](https://packagist.org/packages/quellabs/signal-hub)

A powerful PHP annotation reader for parsing, processing, and caching docblock annotations in PHP classes.

## Overview

The AnnotationReader component provides robust parsing and caching of PHP docblock annotations, allowing you to define metadata directly within your class docblocks. This approach makes your code more self-documenting and reduces the need for separate configuration files.

## Features

- **Annotation parsing**: Parse docblock annotations for classes, properties, and methods
- **Import resolution**: Automatically resolves class imports for fully qualified annotation names
- **Performance optimization**: Implements smart caching to improve performance
- **Flexible integration**: Easy to integrate with your existing projects
- **Error handling**: Graceful handling of malformed annotations
- **Collection support**: Returns immutable AnnotationCollection objects with array-like access

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

### Working with AnnotationCollection

All annotation reader methods return an `AnnotationCollection` object that provides array-like access while handling multiple annotations of the same type elegantly:

```php
$annotations = $reader->getMethodAnnotations(MyClass::class, 'myMethod');

// Array access - returns first annotation of that type
$interceptor = $annotations['InterceptWith'];

// Check if multiple annotations of same type exist
if ($annotations->hasMultiple('InterceptWith')) {
    // Get all annotations of a specific type
    $allInterceptors = $annotations->getAllOfType('InterceptWith');
}

// Iterate through all annotation types
foreach ($annotations as $annotationType => $annotationArray) {
    // $annotationType = 'InterceptWith', $annotationArray = [annotation1, annotation2, ...]
    foreach ($annotationArray as $annotation) {
        // Process individual annotation
    }
}

// Collection methods
$count = count($annotations);
$isEmpty = $annotations->isEmpty();
$firstAnnotation = $annotations->first();
$lastAnnotation = $annotations->last();

// Filtering returns a flat AnnotationCollection with numeric keys
$filtered = $annotations->filter(function($annotation) {
    return $annotation->isActive();
});

// After filtering, access works with numeric indices
$firstFiltered = $filtered[0];
$secondFiltered = $filtered[1];
```

### Handling Multiple Annotations

When you have multiple annotations of the same type, the collection handles them intelligently:

```php
/**
 * @InterceptWith("AuthValidator")
 * @InterceptWith("LoggingInterceptor") 
 * @Route("/api/users")
 */
public function getUsers() { /* ... */ }
```

```php
$annotations = $reader->getMethodAnnotations(MyClass::class, 'getUsers');

// Get first InterceptWith annotation
$firstInterceptor = $annotations['InterceptWith']; 

// Check for multiple InterceptWith annotations
if ($annotations->hasMultiple('InterceptWith')) {
    $allInterceptors = $annotations->getAllOfType('InterceptWith');
    // Returns: [AuthValidator, LoggingInterceptor]
}

// Get single Route annotation
$route = $annotations['Route'];
```

### Filtered Results

When filtering annotations by type, the result is a flat AnnotationCollection with numeric keys for intuitive access:

```php
// This returns a flat AnnotationCollection with [0, 1, 2...] keys
$interceptors = $reader->getMethodAnnotations(MyClass::class, 'myMethod', 'InterceptWith');

// Access filtered results with numeric indices
$first = $interceptors[0];
$second = $interceptors[1];

// Iterate normally
foreach ($interceptors as $index => $annotation) {
    // $index is 0, 1, 2... 
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
     * @Validate("required")
     * @Validate("maxLength", 255)
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