# Signal Hub

A type-safe signal/slot event system for PHP applications. Signal Hub provides a robust mechanism for decoupled communication between components through a centralized hub with strict type checking and priority-based execution.

## Features

- Strong type validation for signal parameters
- Priority-based execution ordering
- Support for wildcards and pattern matching
- Compatible with both object methods and callables
- Lightweight with no external dependencies

## Installation

```bash
composer require quellabs/signal-hub
```

## Basic Usage

### Creating and Emitting Signals

```php
use Quellabs\SignalHub\SignalHub;

// Create a SignalHub instance
$signalHub = new SignalHub();

// Define a signal with its parameter types
$signalHub->signal('user.registered', ['string', User::class]);

// Emit the signal
$signalHub->emit('user.registered', 'registration-source', $userObject);
```

### Connecting to Signals

```php
// Connect a callable to a signal
$signalHub->connect('user.registered', function(string $source, User $user) {
    echo "User {$user->getUsername()} registered from {$source}";
}, null, ['string', User::class]);

// Connect an object method to a signal
$emailService = new EmailService();
$signalHub->connect('user.registered', $emailService, 'sendWelcomeEmail', ['string', User::class]);
```

### Using Priority for Execution Order

```php
// Higher priority executes first
$signalHub->connect('user.registered', $logService, 'logRegistration', ['string', User::class], 100);
$signalHub->connect('user.registered', $emailService, 'sendWelcomeEmail', ['string', User::class], 50);
```

### Wildcard Pattern Matching

```php
// Connect to all user-related signals
$signalHub->connect('user.*', $userLogger, 'logUserActivity', [User::class]);

// This will receive both 'user.registered' and 'user.login' signals
$signalHub->emit('user.registered', $userObject);
$signalHub->emit('user.login', $userObject);
```

### Disconnecting Signals

```php
// Disconnect by connection ID
$connectionId = $signalHub->connect('user.registered', $emailService, 'sendWelcomeEmail');
$signalHub->disconnect('user.registered', $connectionId);

// Disconnect all connections for a receiver
$signalHub->disconnectReceiver('user.registered', $emailService);

// Disconnect specific method of a receiver
$signalHub->disconnectReceiver('user.registered', $emailService, 'sendWelcomeEmail');
```

## Type Safety

Signal Hub enforces strict type checking between signal definitions and slot parameters:

```php
// Signal definition
$signalHub->signal('document.saved', [Document::class, 'bool']);

// This will work - types match
$signalHub->connect('document.saved', function(Document $doc, bool $isNew) {
    // Process document
});

// This will throw an exception - type mismatch
$signalHub->connect('document.saved', function(string $path, bool $isNew) {
    // Wrong first parameter type
});
```

## License

MIT