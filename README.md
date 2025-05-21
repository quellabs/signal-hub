# Quellabs Discover

A flexible service discovery component for PHP applications that helps you automatically discover service providers across your application and its dependencies.

[![PHP Version](https://img.shields.io/packagist/php-v/quellabs/discover.svg)](https://packagist.org/packages/quellabs/discover)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/quellabs/discover.svg)](https://packagist.org/packages/quellabs/discover)
[![License](https://img.shields.io/github/license/quellabs/discover.svg)](https://github.com/quellabs/discover/blob/master/LICENSE.md)

## Introduction

Quellabs Discover is a lightweight package that handles service discovery for PHP applications. It focuses solely on locating service providers defined in your application and its dependencies, giving you complete control over how to use them in your application.

## Installation

You can install the package via composer:

```bash
composer require quellabs/discover
```

## Basic Usage

### Discovering Service Providers

```php
use Quellabs\Discover\Discover;
use Quellabs\Discover\Scanner\ComposerScanner;
use Quellabs\Discover\Scanner\DirectoryScanner;
use Quellabs\Discover\Config\DiscoveryConfig;

// Create a Discover instance
$discover = new Discover();

// Add scanners to discover providers
$discover->addScanner(new ComposerScanner());
$discover->addScanner(new DirectoryScanner([
    __DIR__ . '/app/Providers'
], '/Provider$/'));

// Run the discovery process
$discover->discover();

// Get the discovered providers
$providers = $discover->getProviders();

// Now you can use these providers however you need in your application
foreach ($providers as $provider) {
    // For example, register them with a container
    YourContainer::register($provider);
    
    // Or use them directly
    $serviceNames = $provider->provides();
}
```

### Creating a Service Provider

To create a service provider that can be discovered, implement the `ProviderInterface` or extend the `AbstractProvider` class:

```php
<?php

namespace App\Providers;

use Quellabs\Discover\Provider\AbstractProvider;

class ExampleServiceProvider extends AbstractProvider {
    /**
     * Get the list of services this provider offers
     * @return array<string> List of service identifiers or class names
     */
    public function provides(): array {
        // Return the list of services this provider offers
        return [
            'example.service',
            'App\Services\ExampleService'
        ];
    }
    
    /**
     * Should this provider be loaded?
     * @return bool
     */
    public function shouldLoad(): bool {
        // Conditionally determine if this provider should be included
        return true;
    }
}
```

## Discovery Methods

Quellabs Discover includes multiple ways to discover service providers:

### 1. Composer Configuration

Add service providers to your `composer.json` file:

```json
{
  "name": "your/package",
  "extra": {
    "discover": {
      "providers": [
        "App\\Providers\\ExampleServiceProvider",
        "App\\Providers\\AnotherServiceProvider"
      ]
    }
  }
}
```

Then use the `ComposerScanner`:

```php
$discover->addScanner(new ComposerScanner('discover'));
```

### 2. Directory Scanning

Scan directories for classes that implement `ProviderInterface`:

```php
$discover->addScanner(new DirectoryScanner([
    __DIR__ . '/app/Providers',
    __DIR__ . '/src/Providers'
], '/Provider$/'));
```

## Advanced Configuration

For more control over the discovery process, you can create a custom configuration:

```php
$config = new DiscoveryConfig();

$config->setDebug(true)
       ->setDefaultDirectories([
           __DIR__ . '/app/Providers',
           __DIR__ . '/src/Providers'
       ]);

$discover = new Discover($config);
```

## Using With Frameworks

### Integration with Canvas

Quellabs Discover integrates with the Canvas framework:

```php
// In your Canvas bootstrap file
use Quellabs\Canvas\Container;
use Quellabs\Discover\Discover;

// Create a discover instance
$discover = new Discover();

// Add scanners and discover providers
$discover->addScanner(new ComposerScanner());
$discover->discover();

// Get the providers and register them with Canvas's container
$container = new Container();
foreach ($discover->getProviders() as $provider) {
    $container->register($provider);
}

// Continue with Canvas bootstrap
```

## Extending Discover

### Creating Custom Scanners

You can create custom scanners by implementing the `ScannerInterface`:

```php
<?php

namespace App\Discovery;

use Quellabs\Discover\Scanner\ScannerInterface;
use Quellabs\Discover\Config\DiscoveryConfig;

class CustomScanner implements ScannerInterface {
    public function scan(DiscoveryConfig $config): array {
        // Your custom discovery logic
        // Return an array of ProviderInterface instances
    }
}
```

### Filtering Providers

You can filter providers by the services they provide:

```php
// Get all providers that provide a specific service
$databaseProviders = $discover->findProvidersByService('database');

// You can also filter providers manually
$filteredProviders = array_filter(
    $discover->getProviders(),
    function($provider) {
        return in_array('database', $provider->provides());
    }
);
```

## Provider Interface

The core of the package is the `ProviderInterface`:

```php
<?php

namespace Quellabs\Discover\Provider;

interface ProviderInterface {

    /**
     * Get the services provided by this provider
     * @return array<string> Array of service names or class names
     */
    public function provides(): array;
    
    /**
     * Determine if this provider should be loaded
     * @return bool Whether this provider should be included
     */
    public function shouldLoad(): bool;
}
```

All service providers must implement this interface to be discovered. The interface is intentionally minimal - it only defines methods for:

1. Identifying which services a provider can offer
2. Determining if the provider should be loaded based on runtime conditions

The actual implementation details of how services are created and used are left entirely to your application.

## PSR-4 Utilities

Quellabs Discover includes powerful utilities for working with Composer's autoloader and PSR-4 namespaces. These utilities make it easy to map between file paths and namespaces, discover classes in specific directories, and work with PSR-4 configured directories.

### Working with Composer and PSR-4

#### Getting the Composer Autoloader

Access the Composer `ClassLoader` instance to interact with registered namespaces and paths:

```php
// Get the Composer autoloader
$autoloader = $discover->getComposerAutoloader();
```

#### Finding the Project Root and Composer Configuration

Locate the project's root directory and composer.json file:

```php
// Find the project's root directory (where composer.json is located)
$projectRoot = $discover->getProjectRoot();

// Find the composer.json file, searching upward from the current directory
$composerJsonPath = $discover->getComposerJsonFilePath();

// Or specify a starting directory
$composerJsonPath = $discover->getComposerJsonFilePath('/path/to/start/from');
```

### Namespace <-> Path Mapping

#### Resolving a Namespace from a Path

One of the most useful utilities is mapping a directory path to its corresponding PSR-4 namespace:

```php
// Determine the appropriate namespace for a directory
$namespace = $discover->resolveNamespaceFromPath('/path/to/your/project/src/Controllers');
// Returns something like: "App\Controllers"
```

This method is smart enough to:

1. First try to use the registered Composer autoloader for fast lookups of dependencies
2. Fall back to parsing the main project's `composer.json` directly if needed
3. Find the most specific (longest) matching PSR-4 namespace prefix

The method works for both your project's source code and for directories within dependencies.

### Finding Classes in Directories

Find and load classes in a directory according to PSR-4 autoloading rules:

```php
// Find all classes in a directory based on PSR-4 rules
$classes = $discover->findClassesInDirectory('/path/to/your/Controllers');
// Returns array of fully qualified class names like ["App\Controllers\UserController", ...]

// Optionally filter classes by suffix
$controllerClasses = $discover->findClassesInDirectory(
    '/path/to/your/Controllers',
    'Controller' // Only include classes ending with "Controller"
);
```

This method:

1. Determines the correct namespace for the given directory using PSR-4 rules
2. Recursively scans the directory and all subdirectories
3. Converts file paths to fully qualified class names
4. Filters the results by suffix if requested
5. Only includes classes that actually exist and can be loaded

### Example: Finding All Controller Classes

Here's a complete example showing how to find and load all controller classes in your application:

```php
<?php

use Quellabs\Discover\Discover;

// Create a Discover instance
$discover = new Discover();

// Find all classes ending with "Controller" in your controllers directory
$controllerClasses = $discover->findClassesInDirectory(
    __DIR__ . '/app/Controllers',
    'Controller'
);

// Now you can work with these controller classes
foreach ($controllerClasses as $controllerClass) {
    // Instantiate the controller
    $controller = new $controllerClass();
    
    // Register it, analyze it, or use it however you need
    $yourApp->registerController($controller);
}
```

### How PSR-4 Resolution Works

The PSR-4 utilities resolve namespaces intelligently:

1. First, they check the Composer autoloader's registered PSR-4 prefixes for a match
2. If no match is found in the autoloader, they look in the project's `composer.json` file
3. When multiple matches exist, the most specific (longest matching path) is used
4. The relative path from the matching PSR-4 root is converted to namespace segments

For example, if your `composer.json` has this configuration:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/",
      "App\\Tests\\": "tests/"
    }
  }
}
```

And you call `resolveNamespaceFromPath('/your/project/src/Controllers/UserController.php')`, the library will:

1. Recognize that the path is within the "src/" directory (mapped to "App\\")
2. Convert the relative path "Controllers/UserController.php" to namespace segments
3. Return "App\\Controllers\\UserController" as the fully qualified name

## Practical Use Cases

### Auto-Loading All Controllers

```php
// Auto-discover and register all controllers in your application
$controllers = $discover->findClassesInDirectory(
    __DIR__ . '/app/Controllers',
    'Controller'
);

foreach ($controllers as $controllerClass) {
    $yourRouter->registerController(new $controllerClass());
}
```

### Finding All Implementations of an Interface

```php
// Find all classes that implement a specific interface
$repositoryClasses = $discover->findClassesInDirectory(
    __DIR__ . '/app/Repositories'
);

// Filter to only include classes that implement a specific interface
$repositoryClasses = array_filter($repositoryClasses, function($class) {
    return is_subclass_of($class, YourRepositoryInterface::class);
});
```

### Generating Documentation

```php
// Discover all service provider classes
$providerClasses = $discover->findClassesInDirectory(
    __DIR__ . '/app/Providers',
    'Provider'
);

// Generate documentation for each provider
foreach ($providerClasses as $providerClass) {
    $reflection = new ReflectionClass($providerClass);
    $docComment = $reflection->getDocComment();
    // Process documentation...
}
```

## Advanced PSR-4 Techniques

### Multi-Directory Discovery

```php
// Discover classes across multiple directories
$allServiceClasses = [];

$directories = [
    __DIR__ . '/app/Services',
    __DIR__ . '/src/Core/Services',
    __DIR__ . '/vendor/package/src/Services'
];

foreach ($directories as $directory) {
    $serviceClasses = $discover->findClassesInDirectory($directory, 'Service');
    $allServiceClasses = array_merge($allServiceClasses, $serviceClasses);
}
```

### Conditional Class Loading

```php
// Discover and conditionally load classes
$eventListeners = $discover->findClassesInDirectory(
    __DIR__ . '/app/Listeners'
);

foreach ($eventListeners as $listenerClass) {
    // Only load listeners that are enabled
    $reflection = new ReflectionClass($listenerClass);
    
    if ($reflection->hasMethod('isEnabled') && 
        $listenerClass::isEnabled()) {
        $eventDispatcher->registerListener(new $listenerClass());
    }
}
```

By leveraging these PSR-4 utilities, you can create more modular, extensible applications without hardcoding class paths or manually maintaining class registries.