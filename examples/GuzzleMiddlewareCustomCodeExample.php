<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use LeoCarmo\CircuitBreaker\Adapters\SwooleTableAdapter;
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\GuzzleMiddleware;

$adapter = new SwooleTableAdapter();
$circuit = new CircuitBreaker($adapter, 'httpstat');

$circuit->setSettings([
    'failureRateThreshold' => 2,
]);

// Simulate error
$circuit->failure();

echo "Errors counter: " . $circuit->getFailuresCounter() . PHP_EOL;

$handler = new GuzzleMiddleware($circuit);
$handler->setCustomSuccessCodes([403]);

$handlers = HandlerStack::create();
$handlers->push($handler);

$client = new Client(['handler' => $handlers, 'http_errors' => false]);

$response = $client->get('https://httpstat.us/403');

echo "Response status code: " . $response->getStatusCode() . PHP_EOL;

echo "Errors counter: " . $circuit->getFailuresCounter() . PHP_EOL;