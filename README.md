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

All annotation reader methods return an `AnnotationCollection` object that provides array-like access with a clean, flat structure:

```php
$annotations = $reader->getMethodAnnotations(MyClass::class, 'myMethod');

// Array access by class name - returns first annotation of that type
$interceptor = $annotations[InterceptWith::class];

// Array access by numeric index - returns annotation at that position
$firstAnnotation = $annotations[0];
$secondAnnotation = $annotations[1];

// Iterate through all individual annotations
foreach ($annotations as $annotation) {
    // Each iteration gives you a single annotation object
}

// Get all annotations of a specific type (returns AnnotationCollection)
$allInterceptors = $annotations->all(InterceptWith::class);

// Check if multiple annotations of same type exist
if ($annotations->hasMultiple(InterceptWith::class)) {
    // Process multiple interceptors
}

// Collection methods
$count = count($annotations);
$isEmpty = $annotations->isEmpty();
$firstAnnotation = $annotations->first();
$lastAnnotation = $annotations->last();

// Filtering returns a new AnnotationCollection
$filtered = $annotations->filter(function($annotation) {
    return $annotation->isActive();
});

// Chaining operations
$activeInterceptors = $annotations
    ->all(InterceptWith::class)
    ->filter(fn($interceptor) => $interceptor->isActive());
```

### Handling Multiple Annotations

When you have multiple annotations of the same type, the collection provides clean access patterns:

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
$firstInterceptor = $annotations[InterceptWith::class]; 

// Get all InterceptWith annotations as a collection
$allInterceptors = $annotations->all(InterceptWith::class);

// Check for multiple InterceptWith annotations
if ($annotations->hasMultiple(InterceptWith::class)) {
    foreach ($allInterceptors as $interceptor) {
        // Process each interceptor
    }
}

// Get single Route annotation
$route = $annotations[Route::class];

// Iterate through all annotations (individual objects)
foreach ($annotations as $annotation) {
    // Gets: AuthValidator, LoggingInterceptor, Route
}
```

### Filtered Results

When filtering annotations, the result maintains the same clean structure:

```php
// Filter by specific annotation class
$interceptors = $reader->getMethodAnnotations(MyClass::class, 'myMethod', InterceptWith::class);

// Or filter with custom logic
$activeAnnotations = $annotations->filter(fn($annotation) => $annotation->isActive());

// All results are AnnotationCollection with consistent access
$first = $interceptors[0];              // First annotation
$count = count($interceptors);          // Total count
foreach ($interceptors as $annotation) {
    // Iterate individual annotations
}

// Chain operations
$result = $annotations
    ->filter(fn($a) => $a->isActive())
    ->all(InterceptWith::class);
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