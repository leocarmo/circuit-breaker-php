<?php declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
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

        $client = new Client(['handler' => $handlers, 'verify' => false]);
        $response = $client->get('leocarmo.dev');

        // After a success response the failures must be reset and the circuit is available
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($circuit->isAvailable());

        // Set another failure to ensure that the previous failure was reset and a new fail will not open the circuit
        $circuit->failure();
        $this->assertTrue($circuit->isAvailable());
    }

    public function testSuccessRequestWithCustomStatusCode()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testRequestWithCustomStatusCode');

        // Set the first failure and the failure threshold
        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handler->setCustomSuccessCodes([403]);

        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers, 'verify' => false, 'http_errors' => false]);

        // After a success response the failures must be reset and the circuit is available
        $this->assertEquals(1, $circuit->getFailuresCounter());
        $response = $client->get('https://httpstat.us/403');
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(0, $circuit->getFailuresCounter());
        $this->assertTrue($circuit->isAvailable());

        // Set another failure to ensure that the previous failure was reset and a new fail will not open the circuit
        $circuit->failure();
        $this->assertTrue($circuit->isAvailable());
    }

    public function testSuccessRequestWithCustomStatusCodeFamily()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testRequestWithCustomStatusCode');

        // Set the first failure and the failure threshold
        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handler->setCustomSuccessFamily(500);

        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers, 'verify' => false, 'http_errors' => false]);

        // After a success response the failures must be reset and the circuit is available
        $this->assertEquals(1, $circuit->getFailuresCounter());
        $response = $client->get('https://httpstat.us/502');
        $this->assertEquals(502, $response->getStatusCode());
        $this->assertEquals(0, $circuit->getFailuresCounter());
        $this->assertTrue($circuit->isAvailable());

        // Set another failure to ensure that the previous failure was reset and a new fail will not open the circuit
        $circuit->failure();
        $this->assertTrue($circuit->isAvailable());
    }

    public function testRequestWithIgnoredStatusCode()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testRequestWithCustomStatusCode');

        // Set the first failure and the failure threshold
        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handler->setCustomIgnoreCodes([412]);

        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers, 'verify' => false, 'http_errors' => false]);

        // After an ignored status code, nothing will change on failure counter
        $this->assertEquals(1, $circuit->getFailuresCounter());
        $response = $client->get('https://httpstat.us/412');
        $this->assertEquals(412, $response->getStatusCode());
        $this->assertEquals(1, $circuit->getFailuresCounter());
        $this->assertTrue($circuit->isAvailable());
    }

    public function testRequestWithIgnoredStatusCodeFamily()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testRequestWithCustomStatusCode');

        // Set the first failure and the failure threshold
        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handler->setCustomIgnoreFamily(400);

        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers, 'verify' => false, 'http_errors' => false]);

        // After an ignored status code, nothing will change on failure counter
        $this->assertEquals(1, $circuit->getFailuresCounter());
        $response = $client->get('https://httpstat.us/412');
        $this->assertEquals(412, $response->getStatusCode());
        $this->assertEquals(1, $circuit->getFailuresCounter());
        $this->assertTrue($circuit->isAvailable());
    }

    public function testCircuitIsNotAvailable()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testCircuitIsNotAvailable');

        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();
        $circuit->failure();

        $this->assertEquals(2, $circuit->getFailuresCounter());

        $handler = new GuzzleMiddleware($circuit);
        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers, 'verify' => false]);

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

        $client = new Client(['handler' => $handlers, 'verify' => false]);

        $this->expectException(\GuzzleHttp\Exception\ClientException::class);

        $client->get('leocarmo.dev/undefined');

        $this->assertEquals(2, $circuit->getFailuresCounter());
        $this->assertFalse($circuit->isAvailable());
    }

    public function testFailureRequestToUnknownHost()
    {
        $circuit = new CircuitBreaker(new SwooleTableAdapter(), 'testFailureRequest');

        $circuit->setSettings(['failureRateThreshold' => 2]);
        $circuit->failure();

        $handler = new GuzzleMiddleware($circuit);
        $handlers = HandlerStack::create();
        $handlers->push($handler);

        $client = new Client(['handler' => $handlers, 'verify' => false]);

        try {
            $client->get('undefined_host.dev');
        } catch (\Throwable $exception) {
            $this->assertInstanceOf(ConnectException::class, $exception);
        }

        $this->assertEquals(2, $circuit->getFailuresCounter());
        $this->assertFalse($circuit->isAvailable());
    }
}