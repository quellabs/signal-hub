# Quellabs Discover

[![PHP Version](https://img.shields.io/packagist/php-v/quellabs/discover.svg)](https://packagist.org/packages/quellabs/discover)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/quellabs/discover.svg)](https://packagist.org/packages/quellabs/discover)
[![License](https://img.shields.io/github/license/quellabs/discover.svg)](https://github.com/quellabs/discover/blob/master/LICENSE.md)

A lightweight, flexible service discovery component for PHP applications that automatically discovers service providers across your application and its dependencies with advanced caching and lazy loading capabilities.

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
- [Caching and Performance](#caching-and-performance)
  - [Provider Definition Caching](#provider-definition-caching)
  - [Lazy Loading](#lazy-loading)
  - [Performance Best Practices](#performance-best-practices)
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
- [License](#license)

## Introduction

Quellabs Discover solves the common challenge of service discovery in PHP applications. It focuses solely on locating service providers defined in your application and its dependencies, giving you complete control over how to use these providers in your application architecture. Unlike other service discovery solutions that force specific patterns, Discover is framework-agnostic and can be integrated into any PHP application.

**Key Features:**
- **Lazy Loading**: Providers are only instantiated when actually needed
- **Advanced Caching**: Export and import provider definitions for lightning-fast subsequent loads
- **Framework Agnostic**: Works with any PHP application or framework
- **Multiple Discovery Methods**: Composer configuration, directory scanning, and custom scanners
- **Provider Families**: Organize providers into logical groups
- **PSR-4 Utilities**: Built-in tools for namespace and class discovery

## Installation

Install the package via Composer:

```bash
composer require quellabs/discover
```

## Quick Start

Here's how to quickly get started with Discover:

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
    public function getMetadata(): array {
        return [
            'redis',
        ];
    }
}
```

### Provider Interface

The core `ProviderInterface` is intentionally minimal:

```php
interface ProviderInterface {
    
      /**
       * Retrieves metadata about the provider's capabilities and attributes.
       * This method returns detailed information that describes the provider's
       * functionality, supported features, version information, and other
       * relevant configuration details needed for discovery and integration.
       * @return array<string, mixed> Associative array of metadata key-value pairs
       */
    public function getMetadata(): array;
    
    /**
     * Get default configuration
     * @return array
     */
    public function getDefaults(): array;

    /**
     * Sets configuration
     * @return void
     */
    public function setConfig(array $config): void;
    
    /**
     * Get the family this provider belongs to
     * @return string|null The provider family or null if not categorized
     */
    public function getFamily(): ?string;
    
    /**
     * Set the family for this provider
     * @param string $family The provider family
     * @return void
     */
    public function setFamily(string $family): void;
}
```

This interface specifies:
1. Family classification methods
2. Whether the provider should be loaded
3. Configuration management methods
4. The provider metadata

The actual implementation of how services are created and used is left to your application.

## Discovery Methods

Quellabs Discover supports multiple methods to discover service providers:

### Composer Configuration

Add service providers to your `composer.json` file using the new nested structure where `discover` is always the top-level key:

```json
{
  "name": "your/package",
  "extra": {
    "discover": {
      "default": {
        "providers": [
          "App\\Providers\\ExampleServiceProvider",
          "App\\Providers\\AnotherServiceProvider"
        ]
      }
    }
  }
}
```

Use the `ComposerScanner` to discover these providers:

```php
$discover->addScanner(new ComposerScanner('default'));
```

### Directory Scanning

Scan directories for provider classes:

```php
$discover->addScanner(new DirectoryScanner([
    __DIR__ . '/app/Providers',
    __DIR__ . '/src/Providers'
], '/Provider$/'));
```

## Caching and Performance

Quellabs Discover includes sophisticated caching and lazy loading mechanisms to dramatically improve performance, especially in production environments.

### Provider Definition Caching

The discovery process can be expensive, especially when scanning large codebases or many dependencies. Discover allows you to cache provider definitions and restore them instantly on subsequent runs.

#### Exporting Cache Data

After running discovery, export the provider definitions for caching:

```php
// Perform initial discovery
$discover = new Discover();
$discover->addScanner(new ComposerScanner());
$discover->addScanner(new DirectoryScanner([__DIR__ . '/app/Providers']));
$discover->discover();

// Export definitions for caching
$cacheData = $discover->exportForCache();

// Store in your preferred cache system
file_put_contents('cache/providers.json', json_encode($cacheData));
// Or use Redis, Memcached, etc.
$redis->set('app:providers', serialize($cacheData));
```

#### Importing from Cache

On subsequent requests, skip the discovery process entirely by importing from cache:

```php
// Load from cache
$cacheData = json_decode(file_get_contents('cache/providers.json'), true);
// Or from Redis: $cacheData = unserialize($redis->get('app:providers'));

// Import cached definitions
$discover = new Discover();
$discover->importDefinitionsFromCache($cacheData);

// Providers are now available without running discovery!
$providers = $discover->findProvidersByType('database');
```

### Lazy Loading

**Important**: Discover implements lazy loading by default. This means provider classes are **not instantiated** until you actually request them. This provides significant performance benefits:

```php
// This only stores provider definitions - NO instantiation occurs
$discover->discover();

// Still no instantiation - just returns metadata
$types = $discover->getProviderTypes();

// First instantiation occurs here
$databaseProviders = $discover->findProvidersByType('database');

// Individual providers are cached after first instantiation
$sameProviders = $discover->findProvidersByType('database'); // Uses cached instances
```

#### Understanding Lazy Loading Behavior

```php
// ⚠️ WARNING: This instantiates ALL providers at once
$allProviders = $discover->getProviders(); // Use carefully!

// ✅ RECOMMENDED: Use targeted discovery methods instead
$specificProviders = $discover->findProvidersByType('cache');
$filteredProviders = $discover->findProvidersByMetadata(function($metadata) {
    return in_array('redis', $metadata);
});
```

### Performance Best Practices

#### 1. Use Caching in Production

```php
// Development: Always discover fresh
if (app()->environment('local')) {
    $discover->discover();
} else {
    // Production: Use cache when possible
    $discover->importDefinitionsFromCache($cachedData);
}
```

#### 2. Leverage Lazy Loading

```php
// ❌ Don't do this - instantiates everything
$allProviders = $discover->getProviders();
foreach ($allProviders as $provider) {
    if ($provider->getFamily() === 'database') {
        // Use provider
    }
}

// ✅ Do this - only instantiates what you need
$databaseProviders = $discover->findProvidersByType('database');
```

#### 3. Filter Early

```php
// ✅ Filter by metadata without instantiation
$redisCapableProviders = $discover->findProvidersByMetadata(function($metadata) {
    return in_array('redis', $metadata);
});

// ✅ Combine type and metadata filtering
$cacheRedisProviders = $discover->findProvidersByTypeAndMetadata('cache', function($metadata) {
    return in_array('redis', $metadata);
});
```

#### 4. Cache Structure Optimization

The cache structure is optimized for efficient family-based lookups:

```json
{
  "timestamp": 1640995200,
  "providers": {
    "database": [
      {
        "class": "App\\Providers\\MySQLProvider",
        "family": "database",
        "config": {...},
        "metadata": ["mysql", "pdo"],
        "should_load": true,
        "defaults": {...}
      }
    ],
    "cache": [
      {
        "class": "App\\Providers\\RedisProvider",
        "family": "cache",
        "config": {...},
        "metadata": ["redis", "clustering"],
        "should_load": true,
        "defaults": {...}
      }
    ]
  }
}
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

Specify a configuration file in your `composer.json` using the nested structure:

```json
{
  "extra": {
    "discover": {
      "default": {
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
}
```

### Using Configuration in Providers

Use configuration values in your provider:

```php
class ExampleServiceProvider extends AbstractProvider {

    protected array $config = [];
    
    public function setConfig(array $config): void {
        $this->config = $config;
    }
    
    public function getServiceOptions(): array {
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

Define providers in different families in your `composer.json` using the nested structure:

```json
{
  "extra": {
    "discover": {
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
$databaseProviders = $discover->findProvidersByType('database');

// Get all available families
$families = $discover->getProviderTypes();

// Find providers by both family and capability
$redisProviders = $discover->findProvidersByTypeAndMetadata('cache', function($metadata) {
    return in_array('redis', $metadata);
});
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
        if (!class_exists($className)) {
            return false;
        }
        
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

### Production Optimization Example

```php
// In your application bootstrap
class ApplicationBootstrap {
    public function initializeProviders(): Discover {
        $discover = new Discover();
        
        // Check if we have valid cached provider definitions
        $cacheKey = 'app_providers_' . md5(filemtime('composer.lock'));
        $cached = $this->cache->get($cacheKey);
        
        if ($cached && $this->isProduction()) {
            // Use cached definitions in production
            $discover->importDefinitionsFromCache($cached);
        } else {
            // Perform discovery and cache results
            $discover->addScanner(new ComposerScanner());
            $discover->addScanner(new DirectoryScanner([
                __DIR__ . '/app/Providers'
            ]));
            $discover->discover();
            
            // Cache for future requests
            $this->cache->set($cacheKey, $discover->exportForCache(), 3600);
        }
        
        return $discover;
    }
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

class CustomScanner implements ScannerInterface {
    public function scan(DiscoveryConfig $config): array {
        // Your custom discovery logic
        // Return an array of ProviderInterface instances
    }
}
```

## License

The Quellabs Discover package is open-sourced software licensed under the [MIT license](https://github.com/quellabs/discover/blob/master/LICENSE.md).