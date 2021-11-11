<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use LeoCarmo\CircuitBreaker\Adapters\SwooleTableAdapter;
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\GuzzleMiddleware;

$adapter = new SwooleTableAdapter();
$circuit = new CircuitBreaker($adapter, 'leocarmo.dev');

$circuit->setSettings([
    'timeWindow' => 60,
    'failureRateThreshold' => 2,
    'intervalToHalfOpen' => 30,
]);

$handler = new GuzzleMiddleware($circuit);

$handlers = HandlerStack::create();
$handlers->push($handler);

$client = new Client(['handler' => $handlers]);

$response = $client->get('leocarmo.dev');

var_dump($response->getStatusCode());