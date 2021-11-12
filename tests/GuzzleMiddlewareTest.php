<?php declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use LeoCarmo\CircuitBreaker\Adapters\SwooleTableAdapter;
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\CircuitBreakerException;
use LeoCarmo\CircuitBreaker\GuzzleMiddleware;
use PHPUnit\Framework\TestCase;

class GuzzleMiddlewareTest extends TestCase
{
    public function testSuccessRequest()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testSuccessRequest');

        // Set the first failure and the failure threshold
        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers]);
        $response = $client->get('leocarmo.dev');

        // After a success response the failures must be reset and the circuit is available
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($circuit->isAvailable());

        // Set another failure to ensure that the previous failure was reset and a new fail will not open the circuit
        $circuit->failure();
        $this->assertTrue($circuit->isAvailable());
    }

    public function testCircuitIsNotAvailable()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testCircuitIsNotAvailable');

        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers]);

        $this->expectException(CircuitBreakerException::class);

        $client->get('leocarmo.dev');
    }

    public function testFailureRequest()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testFailureRequest');

        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers]);

        $this->expectException(\GuzzleHttp\Exception\ClientException::class);

        $client->get('leocarmo.dev/undefined');

        $this->assertFalse($circuit->isAvailable());
    }
}