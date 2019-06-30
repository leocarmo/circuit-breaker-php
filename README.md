# PHP implementation of Circuit Breaker Pattern  

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/badges/build.png?b=master)](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/leocarmo/circuit-breaker-php/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)
[![Total Downloads](https://img.shields.io/packagist/dt/leocarmo/circuit-breaker-php.svg)](https://packagist.org/packages/leocarmo/circuit-breaker-php)

For more information about this pattern see [this](https://martinfowler.com/bliki/CircuitBreaker.html).  
  
> This implementation works only with redis adapter yet

## Starting with composer
`composer require leocarmo/circuit-breaker-php`

## Configure redis adapter
The first argument is a redis connection, the second is your product name, for redis namespace avoid key conflicts with another product using the same redis.

```php
use LeoCarmo\CircuitBreaker\CircuitBreaker;

// Connect to redis  
$redis = new \Redis();  
$redis->connect('localhost', 6379);  
  
// Set redis adapter for CB  
CircuitBreaker::setRedisSettings($redis, 'my-product');
```

## Set circuit break settings
> This is not required, default values ​​will be set
```php
// Configure settings for CB  
CircuitBreaker::setGlobalSettings([  
  'timeWindow' => 60, // Time for an open circuit (seconds)  
  'failureRateThreshold' => 50, // Fail rate for open the circuit  
  'intervalToHalfOpen' => 30, // Half open time (seconds)  
]);
```

## Configure settings for specific service
```php
// Configure settings for specific service
CircuitBreaker::setServiceSettings('my-custom-service', [  
  'timeWindow' => 30, // Time for an open circuit (seconds)  
  'failureRateThreshold' => 15, // Fail rate for open the circuit  
  'intervalToHalfOpen' => 10, // Half open time (seconds)  
]);
```

## Check if circuit is available (closed)
Each check is for a specific service. So you can have multiple services in the same application, and when one circuit is open, the other works normally.

```php
// Check circuit status for service: `my-service`
if (! CircuitBreaker::isAvailable('my-service')) {  
  die('Circuit is not available!');  
}
```

## Record success and failure
```php
// Usage example for success and failure  
try {  
  Service::execute('something');  
  CircuitBreaker::success('my-service');  
} catch (\ServiceException $e) {  
  CircuitBreaker::failure('my-service');  
  die($e->getMessage());  
}
```

## Credits
- [Leonardo Carmo](https://github.com/leocarmo)