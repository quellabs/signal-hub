# Quellabs Discover

[![PHP Version](https://img.shields.io/packagist/php-v/quellabs/discover.svg)](https://packagist.org/packages/quellabs/discover)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/quellabs/discover.svg)](https://packagist.org/packages/quellabs/discover)
[![License](https://img.shields.io/github/license/quellabs/discover.svg)](https://github.com/quellabs/discover/blob/master/LICENSE.md)

A lightweight, flexible service discovery component for PHP applications that automatically discovers service providers across your application and its dependencies.

## 📋 Table of Contents

- [Introduction](#introduction)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Service Providers](#service-providers)
    - [Creating a Service Provider](#creating-a-service-provider)
    - [Provider Interface](#provider-interface)
- [Discovery Methods](#discovery-methods)
    - [Composer Configuration](#composer-configuration)
    - [Directory Scanning](#directory-scanning)
- [Provider Configuration](#provider-configuration)
    - [Basic Configuration File](#basic-configuration-file)
    - [Registering Provider with Configuration](#registering-provider-with-configuration)
    - [Using Configuration in Providers](#using-configuration-in-providers)
- [Provider Families](#provider-families)
    - [Defining Provider Families](#defining-provider-families)
    - [Using Multiple Family Scanners](#using-multiple-family-scanners)
    - [Accessing Providers by Family](#accessing-providers-by-family)
- [PSR-4 Utilities](#psr-4-utilities)
    - [Namespace/Path Mapping](#namespacepath-mapping)
    - [Finding Classes in Directories](#finding-classes-in-directories)
    - [Advanced PSR-4 Techniques](#advanced-psr-4-techniques)
- [Framework Integration](#framework-integration)
- [Extending Discover](#extending-discover)
- [Common Use Cases](#common-use-cases)
- [License](#license)

## Introduction

Quellabs Discover solves the common challenge of service discovery in PHP applications. It focuses solely on locating service providers defined in your application and its dependencies, giving you complete control over how to use these providers in your application architecture.

Unlike other service discovery solutions that force specific patterns, Quellabs Discover is framework-agnostic and can be integrated into any PHP application.

## Installation

Install the package via Composer:

```bash
composer require quellabs/discover
```

## Quick Start

Here's how to quickly get started with Quellabs Discover:

```php
use Quellabs\Discover\Discover;
use Quellabs\Discover\Scanner\ComposerScanner;
use Quellabs\Discover\Scanner\DirectoryScanner;

// Create a Discover instance
$discover = new Discover();

// Configure scanners to discover providers
$discover->addScanner(new ComposerScanner());
$discover->addScanner(new DirectoryScanner([
    __DIR__ . '/app/Providers'
], '/Provider$/'));

// Run the discovery process
$discover->discover();

// Get and use the discovered providers
$providers = $discover->getProviders();

foreach ($providers as $provider) {
    // Register with your container or use directly
    $yourContainer->register($provider);
}
```

## Service Providers

### Creating a Service Provider

To create a discoverable service provider, implement the `ProviderInterface` or extend the `AbstractProvider` class:

```php
<?php

namespace App\Providers;

use Quellabs\Discover\Provider\AbstractProvider;

class ExampleServiceProvider extends AbstractProvider {
    /**
     * Get the list of capabilities this provider supports
     * @return array<string>
     */
    public function getCapabilities(): array {
        return [
            'redis',
        ];
    }
    
    /**
     * Determine if this provider should be loaded
     * @return bool
     */
    public function shouldLoad(): bool {
        // Conditionally control provider inclusion
        return true;
    }
}
```

### Provider Interface

The core `ProviderInterface` is intentionally minimal:

```php
interface ProviderInterface {
    /**
     * Get the specific capabilities or services provided by this provider.
     * This returns a list of specific features, services, or capabilities
     * that this provider offers within its broader provider type.
     * @return array<string> Array of service/capability identifiers
     */
    public function getCapabilities(): array;
    
    /**
     * Determine if this provider should be loaded
     * @return bool Whether this provider should be included
     */
    public function shouldLoad(): bool;
}
```

This interface only specifies:
1. Which capabilities a provider supports
2. Whether the provider should be loaded

The actual implementation of how services are created and used is left to your application.

## Discovery Methods

Quellabs Discover supports multiple methods to discover service providers:

### Composer Configuration

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

Use the `ComposerScanner` to discover these providers:

```php
$discover->addScanner(new ComposerScanner('discover'));
```

### Directory Scanning

Scan directories for provider classes:

```php
$discover->addScanner(new DirectoryScanner([
    __DIR__ . '/app/Providers',
    __DIR__ . '/src/Providers'
], '/Provider$/'));
```

## Provider Configuration

Quellabs Discover supports configuration files for providers registered through Composer.

### Basic Configuration File

Create a configuration file that returns an array:

```php
// config/providers/example.php
return [
    'option1' => 'value1',
    'option2' => 'value2',
    'enabled' => true,
    // Any configuration your provider needs
];
```

### Registering Provider with Configuration

Specify a configuration file in your `composer.json`:

```json
{
  "extra": {
    "discover": {
      "providers": [
        {
          "class": "App\\Providers\\ExampleServiceProvider",
          "config": "config/providers/example.php"
        },
        {
          "class": "App\\Providers\\AnotherServiceProvider",
          "config": "config/providers/another.php"
        }
      ]
    }
  }
}
```

### Using Configuration in Providers

Use configuration values in your provider:

```php
class ExampleServiceProvider extends AbstractProvider 
{
    protected array $config = [];
    
    public function setConfig(array $config): void 
    {
        $this->config = $config;
    }
    
    public function shouldLoad(): bool 
    {
        // Use configuration to determine if provider should be loaded
        return $this->config['enabled'] ?? true;
    }
    
    public function getServiceOptions(): array 
    {
        return [
            'option1' => $this->config['option1'] ?? 'default',
            'option2' => $this->config['option2'] ?? 'default',
        ];
    }
}
```

## Provider Families

Provider families organize service providers into logical groups.

### Defining Provider Families

Define providers in different families in your `composer.json`:

```json
{
  "extra": {
    "database": {
      "providers": [
        "App\\Providers\\MySQLProvider",
        "App\\Providers\\PostgreSQLProvider"
      ]
    },
    "cache": {
      "providers": [
        "App\\Providers\\RedisProvider",
        "App\\Providers\\MemcachedProvider"
      ]
    }
  }
}
```

### Using Multiple Family Scanners

Create scanners for each family:

```php
$discover = new Discover();
$discover->addScanner(new ComposerScanner('database'));
$discover->addScanner(new ComposerScanner('cache'));
$discover->discover();
```

### Accessing Providers by Family

Filter providers by family:

```php
// Get all providers of the 'database' family
$databaseProviders = $discover->findProvidersByFamily('database');

// Get all available families
$families = $discover->getProviderFamilies();

// Find providers by both family and capability
$redisProviders = $discover->findProvidersByFamilyAndCapability('cache', 'redis');
```

## PSR-4 Utilities

Quellabs Discover includes utilities for working with PSR-4 namespaces and paths.

### Namespace/Path Mapping

Map between directories and namespaces:

```php
// Get the namespace for a directory
$namespace = $discover->resolveNamespaceFromPath('/path/to/your/project/src/Controllers');
// Returns "App\Controllers" or similar
```

### Finding Classes in Directories

Find classes based on PSR-4 rules:

```php
// Find all controller classes
$controllers = $discover->findClassesInDirectory(
    __DIR__ . '/app/Controllers',
    fn($className) => str_ends_with($className, 'Controller')
);

// Find all classes implementing an interface
$repositories = $discover->findClassesInDirectory(
    __DIR__ . '/app/Repositories',
    fn($className) => class_exists($className) && 
                      is_subclass_of($className, RepositoryInterface::class)
);
```

### Advanced PSR-4 Techniques

Use sophisticated filters for custom class discovery:

```php
// Find only concrete (non-abstract) controller classes with specific methods
$controllers = $discover->findClassesInDirectory(
    __DIR__ . '/app/Controllers',
    function($className) {
        if (!class_exists($className)) return false;
        
        $reflection = new ReflectionClass($className);
        return str_ends_with($className, 'Controller') && 
               !$reflection->isAbstract() && 
               $reflection->hasMethod('handle');
    }
);
```

## Framework Integration

### Integration with Canvas

```php
// In your Canvas bootstrap file
use Quellabs\Canvas\Container;
use Quellabs\Discover\Discover;

$discover = new Discover();
$discover->addScanner(new ComposerScanner());
$discover->discover();

$container = new Container();
foreach ($discover->getProviders() as $provider) {
    $container->register($provider);
}
```

## Extending Discover

### Creating Custom Scanners

Implement the `ScannerInterface` to create custom scanners:

```php
<?php

namespace App\Discovery;

use Quellabs\Discover\Scanner\ScannerInterface;
use Quellabs\Discover\Config\DiscoveryConfig;

class CustomScanner implements ScannerInterface 
{
    public function scan(DiscoveryConfig $config): array 
    {
        // Your custom discovery logic
        // Return an array of ProviderInterface instances
    }
}
```

## Common Use Cases

### Auto-Loading Controllers

```php
// Auto-discover and register all controllers
$controllers = $discover->findClassesInDirectory(
    __DIR__ . '/app/Controllers',
    fn($className) => str_ends_with($className, 'Controller')
);

foreach ($controllers as $controllerClass) {
    $router->registerController(new $controllerClass());
}
```

### Service Manager Organization

```php
// Create service managers for each provider family
$managers = [
    'database' => new DatabaseManager(),
    'cache' => new CacheManager(),
    'queue' => new QueueManager()
];

// Register providers with appropriate managers
foreach ($managers as $family => $manager) {
    foreach ($discover->findProvidersByFamily($family) as $provider) {
        $manager->register($provider);
    }
}
```

## License

The Quellabs Discover package is open-sourced software licensed under the [MIT license](https://github.com/quellabs/discover/blob/master/LICENSE.md).