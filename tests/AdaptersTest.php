<?php declare(strict_types=1);

use LeoCarmo\CircuitBreaker\Adapters\AdapterInterface;
use LeoCarmo\CircuitBreaker\Adapters\RedisClusterAdapter;
use LeoCarmo\CircuitBreaker\Adapters\SwooleTableAdapter;
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use LeoCarmo\CircuitBreaker\Adapters\RedisAdapter;
use PHPUnit\Framework\TestCase;

class AdaptersTest extends TestCase
{
    public function testCreateRedisAdapter()
    {
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'));
        $adapter = new RedisAdapter($redis, 'my-product');

        $this->assertInstanceOf(AdapterInterface::class, $adapter);

        return $adapter;
    }

    public function testCreateRedisClusterAdapter()
    {
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'));
        $adapter = new RedisClusterAdapter($redis, 'my-product');

        $this->assertInstanceOf(AdapterInterface::class, $adapter);

        return $adapter;
    }

    public function testCreateSwooleTableAdapter()
    {
        $adapter = new SwooleTableAdapter();
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
        return $adapter;
    }

    public function provideAdapters()
    {
        return [
            'redis' => [$this->testCreateRedisAdapter()],
            'redis-cluster' => [$this->testCreateRedisClusterAdapter()],
            'swoole-table' => [$this->testCreateSwooleTableAdapter()],
        ];
    }

    /**
     * @dataProvider provideAdapters
     */
    public function testSetAdapter(AdapterInterface $adapter)
    {
        $circuitBreaker = new CircuitBreaker($adapter, 'testSetAdapter');

        $this->assertInstanceOf(AdapterInterface::class, $circuitBreaker->getAdapter());
    }

    /**
     * @dataProvider provideAdapters
     */
    public function testOpenCircuit(AdapterInterface $adapter)
    {
        $circuitBreaker = new CircuitBreaker($adapter, 'testOpenCircuit');

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

    /**
     * @dataProvider provideAdapters
     */
    public function testReachFailureRateAfterTimeWindow(AdapterInterface $adapter)
    {
        $circuitBreaker = new CircuitBreaker($adapter, 'testReachFailureRateAfterTimeWindow');

        $circuitBreaker->setSettings([
            'timeWindow' => 2,
            'failureRateThreshold' => 2,
            'intervalToHalfOpen' => 10,
        ]);

        $circuitBreaker->failure();
        $circuitBreaker->failure();
        $circuitBreaker->failure();

        sleep(3);

        $this->assertTrue($circuitBreaker->isAvailable());
    }

    /**
     * @dataProvider provideAdapters
     */
    public function testCloseCircuitSuccess(AdapterInterface $adapter)
    {
        $circuitBreaker = new CircuitBreaker($adapter, 'testCloseCircuitSuccess');

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

    /**
     * @dataProvider provideAdapters
     */
    public function testHalfOpenFailAndOpenCircuit(AdapterInterface $adapter)
    {
        $circuitBreaker = new CircuitBreaker($adapter, 'testHalfOpenFailAndOpenCircuit');

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
