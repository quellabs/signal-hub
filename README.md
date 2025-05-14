# Sculpt: Modern CLI Framework for Quellabs Ecosystem

<div align="center">

![Sculpt Logo](https://via.placeholder.com/150x150.png?text=Sculpt)

A powerful, extensible command-line toolkit that seamlessly integrates with ObjectQuel ORM.

[![Latest Stable Version](https://img.shields.io/packagist/v/quellabs/sculpt.svg)](https://packagist.org/packages/quellabs/sculpt)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/quellabs/sculpt.svg)](https://packagist.org/packages/quellabs/sculpt)

</div>

## 🚀 Overview

Sculpt provides an elegant command-line interface for rapid development, code generation, and project management within the Quellabs ecosystem. It's designed to be intuitive for beginners yet powerful enough for advanced use cases.

## ✨ Features

- **Unified Command Interface** — Access commands from across the Quellabs ecosystem through a single CLI tool
- **Service Provider Architecture** — Robust plugin system allowing packages to register commands and services
- **Extensible Design** — Built from the ground up for customization and extension
- **Smart Discovery** — Automatically detects and loads commands from installed packages
- **Cross-Package Integration** — Enables seamless interaction between ObjectQuel and other components
- **Developer-Friendly** — Intuitive command structure with helpful documentation and auto-completion
- **Parameter Management** — Sophisticated handling of command-line parameters with validation and type checking

## 📦 Installation

```bash
composer require quellabs/sculpt
```

## 🔍 Quick Start

Once installed, you can run Sculpt commands using:

```bash
vendor/bin/sculpt <command>
```

To see all available commands:

```bash
vendor/bin/sculpt
```

For detailed help on a specific command:

```bash
vendor/bin/sculpt help <command>
```

## 📖 Documentation

### Core Concepts

Sculpt is built around a few key concepts:

1. **Commands** — The primary way users interact with Sculpt
2. **Service Providers** — Register commands and extend functionality
3. **Configuration Manager** — Handles command parameters and options

### Command Structure

Commands in Sculpt follow a namespace pattern:

```
namespace:command
```

For example:
- `db:migrate` — Run database migrations
- `make:model` — Generate a model class
- `cache:clear` — Clear application cache

### Using Command Parameters

Sculpt supports various parameter formats:

```bash
# Named parameters
vendor/bin/sculpt make:model --name=User --table=users

# Flags
vendor/bin/sculpt migrate --force --verbose

# Short flags
vendor/bin/sculpt migrate -fv

# Positional parameters
vendor/bin/sculpt make:controller User
```

## 🔧 Extending Sculpt

### Creating a Service Provider

Sculpt uses a service provider pattern to discover and register commands from packages.

#### 1. Create a Service Provider Class

```php
<?php

namespace Your\Package;

use Quellabs\Sculpt\Application;
use Quellabs\Sculpt\Contracts\ServiceProvider;

class SculptServiceProvider extends ServiceProvider {
    /**
     * Register your package's commands and services
     */
    public function register(Application $app): void {
        // Register commands
        $this->registerCommands($app, [
            \Your\Package\Commands\YourCommand::class,
            \Your\Package\Commands\AnotherCommand::class
        ]);
    }
    
    /**
     * Bootstrap after all providers are registered
     */
    public function boot(Application $app): void {
        // This runs after all providers have been registered
        // Ideal for extending existing commands or services
        if ($app->hasCommand('existing:command')) {
            $command = $app->getCommand('existing:command');
            $command->addTemplate('your-template', __DIR__ . '/templates/example.stub');
        }
    }
}
```

#### 2. Configure Package Discovery

In your package's composer.json:

```json
{
    "name": "your/package",
    "extra": {
        "sculpt": {
            "provider": "Your\\Package\\SculptServiceProvider"
        }
    }
}
```

### Creating Custom Commands

Commands should implement the `CommandInterface` or extend the `BaseCommand` class:

```php
<?php

namespace Your\Package\Commands;

use Quellabs\Sculpt\Commands\BaseCommand;
use Quellabs\Sculpt\ConfigurationManager;

class YourCommand extends BaseCommand {
    /**
     * Get signature of this command
     */
    public function getSignature(): string {
        return 'your:command';
    }
    
    /**
     * Get the description
     */
    public function getDescription(): string {
        return 'Description of your command';
    }
    
    /**
     * Execute the command with parsed configuration
     */
    public function execute(ConfigurationManager $config): int {
        // Access command parameters
        $name = $config->get('name', 'default-name');
        $force = $config->hasFlag('force');
        
        // Display information
        $this->output->writeLn("<bold>Executing command for: {$name}</bold>");
        
        if ($force) {
            $this->output->warning("Force flag is enabled!");
        }
        
        // Command implementation here...
        $this->output->writeLn("<green>Command completed successfully!</green>");
        
        return 0; // Return 0 for success, non-zero for errors
    }
}
```

### Using the Configuration Manager

The `ConfigurationManager` provides a clean way to access command parameters:

```php
// Get a named parameter with a default value
$name = $config->get('name', 'default-value');

// Check if a flag is set
if ($config->hasFlag('force') || $config->hasFlag('f')) {
    // Do something forcefully
}

// Get a positional parameter
$firstArg = $config->getPositional(0);

// Validate parameter format
$email = $config->getValidated('email', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

// Validate parameter is one of allowed values
$env = $config->getEnum('environment', ['development', 'staging', 'production']);

// Require certain parameters
$config->requireParameters(['name', 'type']);
```

## 🤝 Contributing

Contributions are welcome! Here's how you can help:

1. **Report bugs** — Open an issue if you find a bug
2. **Suggest features** — Have an idea? Share it!
3. **Submit PRs** — Fixed something or added a new feature? Submit a pull request

Please ensure your code adheres to our coding standards and includes appropriate tests.

## 📄 License

Sculpt is open-source software licensed under the [MIT license](LICENSE).