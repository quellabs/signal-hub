# Canvas

[![Packagist](https://img.shields.io/packagist/v/quellabs/canvas.svg)](https://packagist.org/packages/quellabs/canvas)

A modern, lightweight PHP framework built on contextual containers with automatic service discovery and ObjectQuel ORM integration.

## The Canvas Difference

Canvas takes a fundamentally different approach to dependency injection and service management. Instead of requiring developers to learn complex service configurations or remember specific service IDs, Canvas uses a **contextual container pattern** that allows you to work directly with interfaces and let the framework intelligently resolve the correct implementation based on context.

```php
// Traditional frameworks require you to know service IDs
$em = $container->get('doctrine.orm.entity_manager');

// Canvas lets you work with interfaces naturally
$em = $app->for('objectquel')->get(EntityManagerInterface::class);
```

## Features

- **🎯 Contextual Containers**: Interface-first service resolution without complex configuration
- **🔍 Automatic Discovery**: Template engines, service providers, and components auto-discovered via Composer
- **🗄️ ObjectQuel ORM**: Modern ORM with Data Mapper pattern and purpose-built query language
- **⚡ Zero Configuration**: Get started immediately with sensible defaults
- **🧩 Modular Architecture**: Add functionality through discoverable packages
- **🏗️ Advanced Autowiring**: Automatic dependency injection throughout the framework
- **🔄 Multiple Implementations**: Seamlessly switch between different service implementations

## Installation

```bash
composer require quellabs/canvas
```

Or create a new project with Canvas:

```bash
mkdir my-canvas-app
cd my-canvas-app
composer init
composer require quellabs/canvas
```

## Quick Start

### Basic Application

```php
<?php
// public/index.php

use Quellabs\Canvas\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
```

### Controller with Route Annotations

Canvas automatically discovers controllers and their routes through annotations:

```php
<?php
// src/Controller/HomeController.php

namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;use Quellabs\Canvas\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends BaseController {
    
    /**
     * @Route("/")
     */
    public function index(): Response {
        return new Response('Hello, Canvas!');
    }
    
    /**
     * @Route("/welcome/{name}")
     */
    public function welcome(string $name): Response {
        return new Response($this->view->render('welcome.tpl', ['name' => $name]));
    }
}
```

Make use of ObjectQuel for queries:

```php
<?php
// src/Controller/UserController.php

namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\Canvas\Controller\BaseController;
use Quellabs\ObjectQuel\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class UserController extends BaseController {
    
    /**
     * @Route("/users")
     */
    public function index(): Response {
        $users = $this->em->findBy(User::class, ['active' => true]);
        return new Response($this->view->render('users/index.tpl', compact('users')));
    }
    
    /**
     * @Route("/users/{id}")
     */
    public function show(int $id): Response {
        $user = $this->em->find(User::class, $id);
        return new Response($this->view->render('users/show.tpl', compact('user')));
    }
}
```

## Contextual Service Resolution

Canvas's most powerful feature is contextual service resolution. When you have multiple implementations of the same interface, you can specify which one to use through context:

### Template Engine Selection

Canvas's contextual container pattern allows the Kernel to dynamically provide different template engines based on configuration or context:

```php
// src/Controller/UserController.php

namespace App\Controller;

use Quellabs\Canvas\Annotations\Route;
use Quellabs\DependencyInjection\Container;
use Quellabs\ObjectQuel\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class UserController {
    
    private $container;
    
    /**
     * Constructor 
     * @param Container $container
     */
    public function __construct(Container $container) {
        $this->container = $this->container;
    }
    
    /**
     * @Route("/users")
     */
    public function index(): Response {
        $em = $this->container->for('objectquel')->get(EntityManagerInterface::class);
        $twig = $this->container->for('twig')->get(TemplateEngineInterface::class);
        
        $users = $em->findBy(User::class, ['active' => true]);

        return new Response($twig->render('users/index.tpl', compact('users')));
    }
}
```

## Automatic Service Discovery

Canvas automatically discovers services, templates engines, and other components through Composer package configurations. This means adding new functionality is as simple as requiring a package.

### Template Engine Discovery

When you install a template engine package, it's automatically available:

```bash
composer require quellabs/canvas-twig
composer require quellabs/canvas-blade  
composer require quellabs/canvas-plates
```

Each package registers itself in `composer.json`:

```json
{
  "extra": {
    "discover": {
      "template_engine": {
        "providers": [
          "Quellabs\\Canvas\\Twig\\TwigServiceProvider",
          "Quellabs\\Canvas\\Blade\\BladeServiceProvider"
        ]
      }
    }
  }
}
```

Canvas automatically discovers and registers these providers, making them available through contextual resolution.

### Custom Service Discovery

You can also register your own discoverable services:

```json
{
  "extra": {
    "discover": {
      "canvas": {
        "providers": [
          "App\\Providers\\CustomCacheProvider",
          "App\\Providers\\PaymentServiceProvider"
        ]
      }
    }
  }
}
```

## Configuration

Canvas follows a convention-over-configuration approach, providing sensible defaults while offering flexibility for customization when your application requires it.

### Creating a Configuration File

Start by creating a configuration file in your project. For this example, we'll place it at `src/config/database.php`:

```php
<?php

return [
    'driver'   => 'mysql',
    'host'     => 'localhost',
    'database' => 'my_database',
    'username' => 'root',
    'password' => 'root',
];
```

This example shows a basic configuration structure. You can define any configuration values your service needs.

### Registering the Configuration

Next, register your configuration file by adding its path to your `composer.json` file:

```json
{
  "extra": {
    "discover": {
      "di": {
        "providers": [
          {
            "class": "Quellabs\\Canvas\\Discover\\ObjectQuelServiceProvider",
            "config": "src/config/database.php"
          }
        ]
      }
    }
  }
}
```

After updating your `composer.json`, run `composer dump-autoload` to ensure the changes are recognized.

## Package Development

Create discoverable packages for Canvas:

```php
<?php
// src/MyPackageServiceProvider.php

use Quellabs\Canvas\ServiceProvider;

class MyPackageServiceProvider extends ServiceProvider {
    
    public function supports(string $className, array $context = []): bool {
        return $className === MyServiceInterface::class
            && ($context['provider'] ?? null) === 'mypackage';
    }
    
    public function createInstance(string $className, array $dependencies): object {
        return new MyService(...$dependencies);
    }
}
```

```json
{
  "extra": {
    "discover": {
      "canvas": {
        "provider": "MyVendor\\MyPackage\\MyPackageServiceProvider"
      }
    }
  }
}
```

## Performance

Canvas is designed for performance:

- **Lazy Loading**: Services are only instantiated when needed
- **Optimized Autowiring**: Efficient reflection caching
- **Minimal Overhead**: Contextual containers add virtually no performance cost
- **ObjectQuel Optimization**: Built-in query caching and optimization
- **Production Caching**: Full service and configuration caching in production

## Contributing

We welcome contributions! Please open an issue or submit a pull request.

## License

Canvas is open-sourced software licensed under the [MIT license](LICENSE).