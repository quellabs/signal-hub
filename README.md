# Sculpt - Command Line Toolkit for the Quellabs Ecosystem

Sculpt is a powerful, extensible CLI framework that seamlessly integrates with ObjectQuel ORM. It provides an elegant command-line interface for rapid development, code generation, and project management.

[![Latest Stable Version](https://img.shields.io/packagist/v/quellabs/sculpt.svg)](https://packagist.org/packages/quellabs/sculpt)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/quellabs/sculpt.svg)](https://packagist.org/packages/quellabs/sculpt)

## Installation

```bash
composer require quellabs/sculpt
```

## Features
- **Unified Command Interface:** Access commands from across the Quellabs ecosystem through a single CLI tool
- **Service Provider Architecture:** Robust plugin system allowing packages to register commands and services
- **Extensible Design:** Built from the ground up for customization and extension
- **Smart Discovery:** Automatically detects and loads commands from installed packages
- **Cross-Package Integration:** Enables seamless interaction between ObjectQuel, Quarry and other components
- **Developer-Friendly:** Intuitive command structure with helpful documentation and auto-completion

## Usage

Once installed, you can run Sculpt commands using:

```bash
vendor/bin/sculpt <command>
```

To list all available commands:

```bash
vendor/bin/sculpt
```

## Creating a Service Provider

Sculpt uses a service provider pattern to discover and register commands from other packages. To make your package's commands available in Sculpt, you need to create a service provider.

### 1. Create a Service Provider Class

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
        $this->commands($app, [
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

### 2. Configure Package Discovery

In your package's composer.json, add the following to enable Sculpt to discover your service provider:

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

### 3. Creating Commands

Commands should implement the `Quellabs\Sculpt\Command` interface or extend the `Quellabs\Sculpt\BaseCommand` class:

```php
<?php

namespace Your\Package\Commands;

use Quellabs\Sculpt\Contracts\CommandInterface;

class YourCommand implements CommandInterface {
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
     * Get the help text
     */
    public function getHelp(): string {
        return 'Detailed help text for your command';
    }
    
    /**
     * Execute the command
     */
    public function execute(array $parameters = []): int {
        $this->output->writeLn('Executing your command...');
        
        // Command implementation
        
        return 0; // Return 0 for success, non-zero for errors
    }
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

Sculpt is open-sourced software licensed under the MIT license.