# SignalHub: Type-Safe Signal-Slot System for PHP

SignalHub is a Qt-inspired signal-slot implementation for PHP with strong type checking and flexible connection options. It allows for loose coupling between components while maintaining type safety.

## Features

- **Type-safe signals and slots**: All connections are checked for type compatibility at runtime
- **Flexible connection patterns**: Support for both direct connections and wildcard patterns
- **Standalone signals**: Create signals independent of objects
- **Object-owned signals**: Define signals as part of your classes
- **Signal discovery**: Find signals by name patterns
- **Priority-based execution**: Control the order of slot execution

## Installation

```bash
composer require quellabs/signalhub
```

## Basic Usage

### Standalone Signals

```php
use Quellabs\SignalHub\SignalHub;

// Create a signal hub for registration and discovery
$hub = new SignalHub();

// Create a standalone signal with a string parameter
$buttonClickedSignal = $hub->createSignal('button.clicked', ['string']);

// Connect a handler to the signal
$buttonClickedSignal->connect(function(string $buttonId) {
    echo "Button clicked: {$buttonId}\n";
});

// Emit the signal
$buttonClickedSignal->emit('submit-button');
```

### Object-Owned Signals

```php
use Quellabs\SignalHub\HasSignals;
use Quellabs\SignalHub\SignalHub;

class Button {
    use HasSignals;
    
    private string $label;
    
    public function __construct(string $label, SignalHub $hub = null) {
        $this->label = $label;
        
        // Define signals for this object
        $this->defineSignal('clicked', ['string']);    // Passes button label
        $this->defineSignal('pressed', ['string']);    // Passes button label
        
        // Register with the hub (optional)
        if ($hub !== null) {
            $this->registerWithHub($hub);
        }
    }
    
    public function click(): void {
        echo "Button '{$this->label}' was clicked\n";
        // Emit the signal with the button label as parameter
        $this->emit('clicked', $this->label);
    }
}

// Create a button
$hub = new SignalHub();
$button = new Button('Submit', $hub);

// Connect to the button's clicked signal
$button->signal('clicked')->connect(function(string $label) {
    echo "Handler received click from '{$label}'\n";
});

// Trigger the button click
$button->click();
```

## Using Patterns for Connection

### Direct Pattern Connection

You can use wildcards to connect to signals based on pattern matching:

```php
// Create several signals
$buttonClickedSignal = $hub->createSignal('button.clicked', ['string']);
$buttonPressedSignal = $hub->createSignal('button.pressed', ['string']);

// Connect handler with a pattern directly on one signal
$buttonClickedSignal->connect(function(string $id) {
    echo "Pattern handler caught button event: {$id}\n";
}, 'button.*');

// This will trigger the handler when buttonClickedSignal is emitted
$buttonClickedSignal->emit('submit-button');

// But NOT when buttonPressedSignal is emitted
$buttonPressedSignal->emit('submit-button'); // Pattern handler not called
```

## Signal Discovery with the Hub

The SignalHub provides powerful signal discovery capabilities:

```php
// Find all signals matching a pattern
$buttonSignals = $hub->findSignals('button.*');

// Connect to all found signals
foreach ($buttonSignals as $name => $signal) {
    echo "Found signal: {$name}\n";
    
    $signal->connect(function(string $id) use ($name) {
        echo "Handler for {$name} triggered with: {$id}\n";
    });
}

// Get info about all registered signals
$allSignals = $hub->getAllSignals();
foreach ($allSignals as $signalInfo) {
    if ($signalInfo['standalone']) {
        echo "Standalone signal: {$signalInfo['name']}\n";
    } else {
        echo "Object signal: {$signalInfo['class']}::{$signalInfo['name']}\n";
    }
}
```

## Advanced Features

### Connection Priorities

Control the order in which slots are executed:

```php
$signal->connect($debugHandler, null, 100);  // Will be called first (higher priority)
$signal->connect($normalHandler, null, 0);   // Will be called second (normal priority)
```

### Type Checking

The system enforces strict type checking:

```php
// Define a signal with specific parameter types
$signal = $hub->createSignal('user.login', ['string', 'int']);

// This will work - types match
$signal->connect(function(string $username, int $userId) {
    echo "User {$username} logged in with ID {$userId}";
});

// This will throw an exception - missing type hint
$signal->connect(function($username, $userId) {
    // Error: Slot parameter 0 is not typed
});

// This will throw an exception - wrong type
$signal->connect(function(string $username, string $userId) {
    // Error: Type mismatch for parameter 1
});
```

### Disconnecting Handlers

```php
// Connect a handler
$signal->connect($handler, 'handleEvent');

// Disconnect the handler
$signal->disconnect($handler, 'handleEvent');

// Or disconnect all handlers for this receiver
$signal->disconnect($handler);
```

## Architecture Overview

The system consists of three main components:

1. **SignalHub**: Registry for signal creation and discovery
    - `createSignal()` - Create standalone signals
    - `findSignals()` - Find signals by pattern
    - `getStandaloneSignal()` / `getObjectSignal()` - Retrieve specific signals
    - `registerSignal()` - Register signals with the hub

2. **Signal**: Core signal functionality
    - `connect()` - Connect handlers (callable or object methods)
    - `emit()` - Emit the signal with parameters
    - `disconnect()` - Remove connections

3. **HasSignals trait**: Makes any class capable of having signals
    - `defineSignal()` - Create signals owned by the object
    - `emit()` - Emit object signals
    - `signal()` - Get a specific signal
    - `registerWithHub()` - Register with a SignalHub

## Differences from Qt

While inspired by Qt, there are some differences:

1. PHP doesn't support compile-time signal/slot connections, so all type checking is done at runtime
2. The pattern matching functionality is a PHP-specific extension not available in Qt
3. The SignalHub concept provides discovery capabilities beyond what Qt offers

## Examples

### Form Validation Example

```php
class Form {
    use HasSignals;
    
    private string $name;
    
    public function __construct(string $name, SignalHub $hub = null) {
        $this->name = $name;
        
        $this->defineSignal('submitted', ['string', 'array']); // form name, form data
        $this->defineSignal('validated', ['string', 'bool']);  // form name, is valid
        
        if ($hub !== null) {
            $this->registerWithHub($hub);
        }
    }
    
    public function submit(array $data): void {
        // Emit submitted signal
        $this->emit('submitted', $this->name, $data);
        
        // Validate data
        $isValid = $this->validate($data);
        
        // Emit validation result
        $this->emit('validated', $this->name, $isValid);
    }
    
    private function validate(array $data): bool {
        // Example validation
        return !empty($data);
    }
}

// Create form and connect signals
$hub = new SignalHub();
$loginForm = new Form('login', $hub);

// Connect to form submission
$loginForm->signal('submitted')->connect(function(string $formName, array $data) {
    echo "Form {$formName} was submitted with data: " . json_encode($data) . "\n";
});

// Connect to validation result
$loginForm->signal('validated')->connect(function(string $formName, bool $isValid) {
    if ($isValid) {
        echo "Form {$formName} is valid\n";
    } else {
        echo "Form {$formName} has errors\n";
    }
});

// Submit the form
$loginForm->submit(['username' => 'john', 'password' => 'secret']);
```

## Best Practices

1. Use meaningful signal names, preferably in dot notation (e.g., 'user.login', 'form.submitted')
2. Always add type hints to signal handlers
3. Use the HasSignals trait for object-owned signals
4. Register important signals with the SignalHub for discovery
5. Use pattern matching for logging and debugging purposes
6. Keep signals focused on specific events
7. Consider using priorities for handlers that need to run first or last

## License

This library is licensed under the MIT License.