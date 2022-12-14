# PHP implementation of Circuit Breaker Pattern  

[![Build Status](https://travis-ci.org/leocarmo/circuit-breaker-php.svg?branch=master)](https://travis-ci.org/leocarmo/circuit-breaker-php)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/?branch=master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)
[![Total Downloads](https://img.shields.io/packagist/dt/leocarmo/circuit-breaker-php.svg)](https://packagist.org/packages/leocarmo/circuit-breaker-php)

For more information about this pattern see [this](https://martinfowler.com/bliki/CircuitBreaker.html).

## Starting with composer
```sh
composer require leocarmo/circuit-breaker-php
```

## Adapters

- [Redis](#redis-adapter) 
- [Redis Cluster](#redis-cluster-adapter) 
- [Swoole Table](#swooletable-adapter)

### Redis Adapter
The first argument is a redis connection, the second is your product name, for redis namespace avoid key conflicts with another product using the same redis.

```php
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\Adapters\RedisAdapter;

// Connect to redis
$redis = new \Redis();
$redis->connect('localhost', 6379);

$adapter = new RedisAdapter($redis, 'my-product');

// Set redis adapter for CB
$circuit = new CircuitBreaker($adapter, 'my-service');
```

> See [this](examples/RedisAdapterExample.php) for full example

### Redis Cluster Adapter
Without use of [`multi`](https://redis.io/commands/multi/) command.
The first argument is a redis connection, the second is your product name, for redis namespace avoid key conflicts with another product using the same redis.

```php
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\Adapters\RedisClusterAdapter;

// Connect to redis
$redis = new \Redis();
$redis->connect('localhost', 6379);

$adapter = new RedisClusterAdapter($redis, 'my-product');

// Set redis adapter for CB
$circuit = new CircuitBreaker($adapter, 'my-service');
```

> See [this](examples/RedisClusterAdapterExample.php) for full example

### SwooleTable Adapter

```php
use LeoCarmo\CircuitBreaker\CircuitBreaker;

$circuit = new CircuitBreaker(new SwooleTableAdapter(), 'my-service');
```

## Guzzle Middleware

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use LeoCarmo\CircuitBreaker\GuzzleMiddleware;

$handler = new GuzzleMiddleware($circuit);

$handlers = HandlerStack::create();
$handlers->push($handler);

$client = new Client(['handler' => $handlers]);

$response = $client->get('leocarmo.dev');
```

*Important*: all status code between 200 and 299 will be recorded as a success, and other status will be recorded as a failure.

> See [this](examples/GuzzleMiddlewareExample.php) for full example

### Customize success status code 

If you need to specify a custom status code that is not a failure, you can use:

```php
$handler = new GuzzleMiddleware($circuit);
$handler->setCustomSuccessCodes([400]);
```

*Important:* this configuration will record a success when a status code `400` is returned

> See [this](examples/GuzzleMiddlewareCustomCodeExample.php) for full example

### Ignore status code

If you want to ignore the status code returned and not record a success or failure, use this:

```php
$handler = new GuzzleMiddleware($circuit);
$handler->setCustomIgnoreCodes([412]);
```

## Set circuit break settings
> This is not required, default values will be set
```php
$circuit->setSettings([
    'timeWindow' => 60, // Time for an open circuit (seconds)
    'failureRateThreshold' => 50, // Fail rate for open the circuit
    'intervalToHalfOpen' => 30,  // Half open time (seconds)
]);
```

## Check if circuit is available (closed)
Each check is for a specific service. So you can have multiple services in the same application, and when one circuit is open, the other works normally.

```php
// Check circuit status for service
if (! $circuit->isAvailable()) {
    die('Circuit is not available!');
}
```

## Record success and failure
```php
// Usage example for success and failure  
try {
    myService();
    $circuit->success();
} catch (RuntimeException $e) {
    // If an error occurred, it must be recorded as failure.
    $circuit->failure();
}
```

## Development

### Setup
```shell
make setup
```

### Tests

```sh 
make test 
```

## Contributors
<a href="https://github.com/leocarmo/circuit-breaker-php/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=leocarmo/circuit-breaker-php&max=10" />
</a>
