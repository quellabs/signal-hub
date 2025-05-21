# Provider Families and Capabilities

Quellabs Discover now supports organizing service providers into families, creating a powerful two-level classification system for your application's services.

## Understanding Provider Families

A provider family represents a group of related providers that share a common purpose or domain. Families help you organize your providers into logical categories, making them easier to manage and discover.

## Setting a Provider Family

You can specify a provider family in your `composer.json` file:

```json
{
  "name": "your/package",
  "extra": {
    "discover": {
      "provider": "App\\Providers\\CacheServiceProvider",
      "config": "config/providers/cache.php",
      "family": "cache"
    }
  }
}
```

For multiple providers:

```json
{
  "name": "your/package",
  "extra": {
    "discover": {
      "providers": [
        {
          "class": "App\\Providers\\RedisServiceProvider",
          "config": "config/providers/redis.php",
          "family": "cache"
        },
        {
          "class": "App\\Providers\\MySQLServiceProvider",
          "config": "config/providers/mysql.php",
          "family": "database"
        }
      ]
    }
  }
}
```

## Implementing Family Support in Providers

Your providers will automatically receive family information if they implement the updated `ProviderInterface` or extend the `AbstractProvider` class:

```php
<?php

namespace App\Providers;

use Quellabs\Discover\Provider\AbstractProvider;

class CacheServiceProvider extends AbstractProvider {
    public function getCapabilities(): array {
        return ['redis', 'distributed-lock', 'pubsub'];
    }
    
    public function shouldLoad(): bool {
        return true;
    }
    
    // The family will be automatically set from the composer.json configuration
    // You can also set it manually in your provider:
    public function __construct() {
        $this->family = 'cache';
    }
}
```

## The Two-Level Classification System

Quellabs Discover now implements a two-level classification system:

1. **Provider Family** - The broad group of related providers (returned by `getFamily()`)
2. **Provider Capabilities** - The specific features each provider offers (returned by `getCapabilities()`)

This distinction creates a powerful hierarchical organization system for your service providers.

### Provider Family

The provider family represents a high-level category that describes the general domain:

```php
class RedisProvider extends AbstractProvider {
    public function __construct() {
        $this->family = 'cache';  // This provider is in the "cache" family
    }
}
```

Common provider families might include:
- `database` - Database connection and management
- `cache` - Caching mechanisms
- `queue` - Job/task queue processing
- `auth` - Authentication systems

### Provider Capabilities

The capabilities represent specific features or services within that family:

```php
class RedisProvider extends AbstractProvider {
    public function __construct() {
        $this->family = 'cache';
    }
    
    public function getCapabilities(): array {
        return [
            'redis',             // Indicates it provides Redis functionality
            'distributed-lock',  // Has distributed locking capability
            'pubsub'             // Supports publish/subscribe patterns
        ];
    }
}
```

## Finding Providers

Once you've configured your providers with families and capabilities, you can easily filter them:

```php
// Get all providers of a specific family
$cacheProviders = $discover->findProvidersByFamily('cache');
$databaseProviders = $discover->findProvidersByFamily('database');

// Get all available provider families in your application
$families = $discover->getProviderFamilies();

// Get all providers with a specific capability
$redisProviders = $discover->findProvidersByCapability('redis');

// Find providers by both family and capability
$redisInCacheFamily = $discover->findProvidersByFamilyAndCapability('cache', 'redis');
```

## Best Practices

### For Provider Families:
- Use broad, general categories
- Keep the number of families relatively small
- Use lowercase, simple names
- Consider standardizing a set of approved families in your organization

### For Provider Capabilities:
- Be specific about what functionality is offered
- Use a consistent naming convention
- Include both general capabilities and specific implementations
- Consider including version information for critical features

### Example Family/Capability Combinations:

| Provider Family | Example Capabilities |
|----------------|----------------------|
| `database`     | `mysql`, `postgresql`, `transactions`, `migrations` |
| `cache`        | `redis`, `memcached`, `local`, `distributed-lock` |
| `queue`        | `sqs`, `rabbitmq`, `delayed-jobs`, `retries` |
| `storage`      | `s3`, `local`, `streaming`, `encryption` |
| `mail`         | `smtp`, `ses`, `templates`, `attachments` |

## Example: Family-Based Provider Registration

```php
// Create service managers for each family
$managers = [
    'database' => new DatabaseManager(),
    'cache' => new CacheManager(),
    'queue' => new QueueManager()
];

// Register providers with their appropriate managers
foreach ($managers as $family => $manager) {
    foreach ($discover->findProvidersByFamily($family) as $provider) {
        $manager->register($provider);
    }
}
```

This approach creates a clean, organized way to group and manage related services in your application.