<?php

use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\Adapters\RedisAdapter;

class RedisAdapterTest extends \PHPUnit\Framework\TestCase
{

    public function testSetAdapter()
    {
        $redis = new \Redis();
        $redis->connect('0.0.0.0', 6379);

        $redis = new RedisAdapter($redis, 'my-product');

        CircuitBreaker::setAdapter($redis);

        $this->assertInstanceOf(RedisAdapter::class, CircuitBreaker::getAdapter());
    }

    public function testOpenCircuit()
    {
        $service = 'my-service';

        CircuitBreaker::setServiceSettings($service, [
            'timeWindow' => 20,
            'failureRateThreshold' => 5,
            'intervalToHalfOpen' => 10,
        ]);

        CircuitBreaker::failure($service);
        CircuitBreaker::failure($service);
        CircuitBreaker::failure($service);
        CircuitBreaker::failure($service);
        CircuitBreaker::failure($service);

        $this->assertFalse(CircuitBreaker::isAvailable($service));
    }

    public function testCloseCircuitSuccess()
    {
        CircuitBreaker::success('my-service');

        $this->assertTrue(CircuitBreaker::isAvailable('my-service'));
    }

    public function testHalfOpenFailAndOpenCircuit()
    {
        $service = 'my-service-2';

        CircuitBreaker::setServiceSettings($service, [
            'timeWindow' => 1,
            'failureRateThreshold' => 3,
            'intervalToHalfOpen' => 15,
        ]);

        CircuitBreaker::failure($service);
        CircuitBreaker::failure($service);
        CircuitBreaker::failure($service);

        // Check if is available for open circuit
        $this->assertFalse(CircuitBreaker::isAvailable($service));

        // Sleep for half open
        sleep(2);

        // Register new failure
        CircuitBreaker::failure($service);

        // Check if is open
        $this->assertFalse(CircuitBreaker::isAvailable($service));
    }
}
