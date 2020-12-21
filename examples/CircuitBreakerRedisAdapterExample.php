<?php
require_once __DIR__ . '/../vendor/autoload.php';

use LeoCarmo\CircuitBreaker\CircuitBreaker;

// Connect to redis
$redis = new \Redis();
$redis->connect('localhost', 6379);

$adapter = new \LeoCarmo\CircuitBreaker\Adapters\RedisAdapter($redis, 'my-product');

// Set redis adapter for CB
CircuitBreaker::setAdapter($adapter);

// Configure settings for CB
CircuitBreaker::setGlobalSettings([
    'timeWindow' => 60, // Time for an open circuit (seconds)
    'failureRateThreshold' => 50, // Fail rate for open the circuit
    'intervalToHalfOpen' => 30,  // Half open time (seconds)
]);

// Configure settings for specific service
CircuitBreaker::setServiceSettings('my-custom-service', [
    'timeWindow' => 30, // Time for an open circuit (seconds)
    'failureRateThreshold' => 15, // Fail rate for open the circuit
    'intervalToHalfOpen' => 10,  // Half open time (seconds)
]);

// Check circuit status for service: `my-service`
if (! CircuitBreaker::isAvailable('my-service')) {
    die('Circuit is not available!');
}

// Usage example for success and failure
try {
    \Service::execute();
    CircuitBreaker::success('my-service');
} catch (\ServiceException $e) {
    // If an error occurred, it must be recorded as failure.
    CircuitBreaker::failure('my-service');
    die($e->getMessage());
}