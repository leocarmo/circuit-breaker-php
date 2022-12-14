<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\Adapters\RedisClusterAdapter;

// Connect to redis
$redis = new \Redis();
$redis->connect('localhost', 6379);

$adapter = new RedisClusterAdapter($redis, 'my-product');

// Set redis adapter for CB
$circuit = new CircuitBreaker($adapter, 'my-service');

// Configure settings for CB
$circuit->setSettings([
    'timeWindow' => 60, // Time for an open circuit (seconds)
    'failureRateThreshold' => 50, // Fail rate for open the circuit
    'intervalToHalfOpen' => 30,  // Half open time (seconds)
]);

// Check circuit status for service
if (! $circuit->isAvailable()) {
    die('Circuit is not available!');
}

// Usage example for success and failure
function myService() {
    if (rand(1, 100) >= 50) {
        throw new RuntimeException('Something got wrong!');
    }
}

try {
    myService();
    $circuit->success();
    echo 'success!' . PHP_EOL;
} catch (RuntimeException $e) {
    // If an error occurred, it must be recorded as failure.
    $circuit->failure();
    echo 'fail!' . PHP_EOL;
}