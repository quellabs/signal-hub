# Quellabs Dependency Injection

A lightweight, PSR-compliant dependency injection container for PHP with advanced autowiring capabilities.

## Features

- **Autowiring**: Automatically resolve dependencies through reflection
- **Service Providers**: Customize how specific services are instantiated
- **Service Discovery**: Automatically discover service providers from Composer configurations
- **Circular Dependency Detection**: Prevents infinite loops in dependency graphs
- **Method Injection**: Support for dependency injection in any method, not just constructors
- **Default Service Fallback**: Automatically handle classes with no dedicated provider
- **Singleton by Default**: The default service provider resolves all classes as singletons

## Installation

```bash
composer require quellabs/dependency-injection
```

## Basic Usage

```php
// Create a container
$container = new \Quellabs\DependencyInjection\Container();

// Get a service (automatically resolves all dependencies)
$service = $container->get(MyService::class);

// Call a method with autowired dependencies
$result = $container->call($service, 'doSomething', ['extraParam' => 'value']);
```

## Service Providers

Service providers allow you to customize how services are created. A service provider can:

- Define specific instantiation logic for a service
- Support instantiation of interfaces

### Default Service Provider

By default, all classes without a dedicated service provider are handled by the `DefaultServiceProvider`, which implements a singleton pattern. This means that for any given class, only one instance will ever be created and shared across the application.

### Creating a Service Provider

```php
/**
 * Service Provider class for dependency injection
 * Extends the base ServiceProvider from Quellabs eco system
 */
use Quellabs\DependencyInjection\Provider\ServiceProvider;

/**
 * Custom service provider that handles instantiation of specific services
 */
class MyServiceProvider extends ServiceProvider {

    /**
     * Determines if this provider can create the requested class
     * @param string $className The fully qualified class name to check
     * @return bool True if this provider supports creating the class
     */
    public function supports(string $className): bool {
        // Support either the exact MyService class or any class implementing MyInterface
        return $className === MyService::class || is_subclass_of($className, MyInterface::class);
    }
    
    /**
     * Creates an instance of the requested class with dependencies injected
     * @param string $className The fully qualified class name to instantiate
     * @param array $dependencies Array of dependencies to inject into the constructor
     * @return object The instantiated object
     */
    public function createInstance(string $className, array $dependencies): object {
        // Instantiate the class by passing all dependencies to the constructor
        $instance = new $className(...$dependencies);
        
        // Apply post-instantiation configuration for specific service types
        if ($instance instanceof MyService) {
            // Call an initialization method if the instance is MyService
            $instance->initialize();
        }
        
        // Return the fully configured instance
        return $instance;
    }
}
```

### Registering a Service Provider

```php
$container->register(new MyServiceProvider($container));
```

## Automatic Service Discovery

The container can automatically discover and register service providers through multiple methods. The Dependency Injection package integrates the Quellabs Discover functionality, giving you powerful service discovery capabilities right out of the box.

### Basic Discovery with Composer Configuration

#### Project-Level Configuration

In your `composer.json`:

```json
{
    "extra": {
        "di": {
            "providers": [
                "App\\Providers\\MyServiceProvider",
                "App\\Providers\\DatabaseServiceProvider"
            ]
        }
    }
}
```

#### Package-Level Configuration

For packages that want to register providers when installed:

```json
{
    "extra": {
        "di": {
            "provider": "MyPackage\\MyPackageServiceProvider"
        }
    }
}
```

For more information about Quellabs Discover and its advanced features, visit [https://github.com/quellabs/discover](https://github.com/quellabs/discover).

## Singleton and Transient Patterns

Since the default provider already implements the singleton pattern, you may want to create a custom provider for transient (non-singleton) services:

```php
use Quellabs\DependencyInjection\Provider\ServiceProvider;

/**
 * TransientServiceProvider specializes in providing non-singleton instances.
 * When a class is supported by this provider, a new instance will be created
 * for each request/resolution rather than being cached and reused.
 */
class TransientServiceProvider extends ServiceProvider {
    
    /**
     * Determines if this provider should handle the requested class.
     * @param string $className The fully qualified class name to check
     * @return bool True if this provider should create the instance
     */
    public function supports(string $className): bool {
        // Define which classes should be created as new instances each time
        // These are typically stateful classes that shouldn't be shared between requests
        return in_array($className, [
            RequestContext::class,    // Contains request-specific data
            TemporaryData::class      // Holds temporary state that shouldn't persist
        ]);
    }
    
    /**
     * Creates a new instance of the requested class.
     * @param string $className The class to instantiate
     * @param array $dependencies Array of constructor dependencies already resolved
     * @return object A new instance of the requested class
     */
    public function createInstance(string $className, array $dependencies): object {
        // Always create a new instance without caching
        // The spread operator (...) unpacks the dependencies array as arguments
        return new $className(...$dependencies);
    }
}
```

## Advanced Configuration

### Debug Mode

Enable debug mode to see detailed error information:

```php
$container = new \Quellabs\DependencyInjection\Container(null, true);
```

### Custom Base Path

Specify a custom base path for service discovery:

```php
$container = new \Quellabs\DependencyInjection\Container('/path/to/app');
```

### Custom Configuration Key

Use a custom key for service discovery in composer.json:

```php
$container = new \Quellabs\DependencyInjection\Container(null, false, 'custom-key');
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License