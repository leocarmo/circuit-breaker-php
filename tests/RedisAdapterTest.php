<?php declare(strict_types=1);

use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\Adapters\RedisAdapter;
use PHPUnit\Framework\TestCase;

class RedisAdapterTest extends TestCase
{
    protected function createRedisAdapter()
    {
        $redis = new \Redis();
        $redis->connect('0.0.0.0', 6379);
        return new RedisAdapter($redis, 'my-product');
    }

    public function testSetAdapter()
    {
        $redis = $this->createRedisAdapter();

        $circuitBreaker = new CircuitBreaker($redis, 'testSetAdapter');

        $this->assertInstanceOf(RedisAdapter::class, $circuitBreaker->getAdapter());
    }

    public function testOpenCircuit()
    {
        $redis = $this->createRedisAdapter();

        $circuitBreaker = new CircuitBreaker($redis, 'testOpenCircuit');

        $circuitBreaker->setSettings([
            'timeWindow' => 20,
            'failureRateThreshold' => 5,
            'intervalToHalfOpen' => 10,
        ]);

        $circuitBreaker->failure();
        $circuitBreaker->failure();
        $circuitBreaker->failure();
        $circuitBreaker->failure();
        $circuitBreaker->failure();

        $this->assertFalse($circuitBreaker->isAvailable());
    }

    public function testCloseCircuitSuccess()
    {
        $redis = $this->createRedisAdapter();

        $circuitBreaker = new CircuitBreaker($redis, 'testCloseCircuitSuccess');

        $circuitBreaker->setSettings([
            'timeWindow' => 20,
            'failureRateThreshold' => 1,
            'intervalToHalfOpen' => 10,
        ]);

        $circuitBreaker->failure();
        $circuitBreaker->failure();

        $this->assertFalse($circuitBreaker->isAvailable());

        $circuitBreaker->success();

        $this->assertTrue($circuitBreaker->isAvailable());
    }

    public function testHalfOpenFailAndOpenCircuit()
    {
        $redis = $this->createRedisAdapter();

        $circuitBreaker = new CircuitBreaker($redis, 'testHalfOpenFailAndOpenCircuit');

        $circuitBreaker->setSettings([
            'timeWindow' => 1,
            'failureRateThreshold' => 3,
            'intervalToHalfOpen' => 15,
        ]);

        $circuitBreaker->failure();
        $circuitBreaker->failure();
        $circuitBreaker->failure();

        // Check if is available for open circuit
        $this->assertFalse($circuitBreaker->isAvailable());

        // Sleep for half open
        sleep(2);

        // Register new failure
        $circuitBreaker->failure();

        // Check if is open
        $this->assertFalse($circuitBreaker->isAvailable());
    }
}
