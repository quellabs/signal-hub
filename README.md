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
$databaseProviders = $discover->getProvidersForService('database');

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

## PSR-4 and Class Discovery

Quellabs Discover includes utilities for working with Composer's autoloader and PSR-4 namespaces:

### Accessing the Composer Autoloader

```php
// Get the Composer ClassLoader instance
$autoloader = $discover->getComposerAutoloader();
```

### Scanning Directories with PSR-4 Mapping

```php
// Scan a directory and map files to fully qualified class names using PSR-4 rules
$classes = $discover->scanDirectoryWithPsr4(
    __DIR__ . '/app/Controllers', 
    $prefixes,
    'Controller'  // Optional suffix to filter classes
);
```

### Mapping Directories to Namespaces

```php
// Map a directory path to its corresponding namespace based on PSR-4 rules
$namespace = $discover->mapDirectoryToNamespace(
    __DIR__ . '/app/Controllers',
    __DIR__ . '/app',
    'App\\'
);
```

### Finding Namespace from File Path

```php
// Convert a file path to its fully qualified namespace based on PSR-4 rules
$namespace = $discover->findNamespaceFromPath(__DIR__ . '/app/Services/UserService.php');
// Returns: "App\Services\UserService"
```

The `findNamespaceFromPath` method examines Composer's PSR-4 autoloader configuration to determine the correct namespace for a given PHP file. It maps file paths to their corresponding fully qualified namespaces by:

1. Getting Composer's PSR-4 prefix configurations
2. Finding which base directory contains the file
3. Converting the relative path to a namespace segment
4. Combining the namespace prefix with the path segment

This is particularly useful when you have a file path and need to determine its corresponding class name based on PSR-4 autoloading rules.

### Example: Finding Controller Classes

```php
/**
 * Maps directory structure to namespaces based on the PSR-4 autoload configuration
 * @param string $dir Absolute path to the directory to scan
 * @param string $controllerSuffix Optional suffix to filter controller classes (e.g., 'Controller')
 * @return array<string> Array with fully qualified class names
 * @throws \RuntimeException If the directory isn't readable
 */
protected function findControllerClasses(string $dir, string $controllerSuffix = 'Controller'): array {
    if (!is_readable($dir)) {
        throw new \RuntimeException("Directory not readable: {$dir}");
    }
    
    // Get the Composer autoloader
    $composerAutoloader = $this->getComposerAutoloader();
    
    // Get PSR-4 prefixes from the autoloader
    $prefixesPsr4 = $composerAutoloader->getPsr4Prefixes($composerAutoloader);
    
    // Scan directory and map to namespaces
    return $this->scanDirectoryWithPsr4($dir, $prefixesPsr4, $controllerSuffix);
}
``` 