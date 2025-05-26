# Canvas Smarty Template Engine

A Smarty template engine integration for the Canvas PHP framework with automatic service discovery and dependency injection.

## Installation

```bash
composer require quellabs/canvas-smarty
```

## Requirements

- PHP 8.3 or higher
- Canvas framework with service discovery

## Usage

The Smarty template engine is automatically registered with Canvas through the service discovery system. No manual configuration required.

```php
// In your Canvas controller
class HomeController
{
    public function index(SmartyTemplateProvider $smarty)
    {
        return $smarty->render('home.tpl', [
            'title' => 'Welcome to Canvas',
            'user' => $user
        ]);
    }
}
```

## Template Files

Place your Smarty templates in your Canvas application's template directory:

```
templates/
├── home.tpl
├── layouts/
│   └── app.tpl
└── partials/
    └── header.tpl
```

## License

MIT License