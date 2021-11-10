<?php declare(strict_types=1);

use LeoCarmo\CircuitBreaker\Adapters\AdapterInterface;
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function createCircuitBreaker(): CircuitBreaker
    {
        $adapter = $this->createMock(AdapterInterface::class);
        return new CircuitBreaker($adapter, 'service');
    }

    public function testGetAdapter()
    {
        $expected = $this->createMock(AdapterInterface::class);

        $circuitBreaker = new CircuitBreaker($expected, 'service');

        $adapter = $circuitBreaker->getAdapter();

        static::assertEquals($expected, $adapter);
    }

    public function testGetSettings()
    {
        $settings = ['timeWindow' => 10];

        $circuitBreaker = $this->createCircuitBreaker();

        $circuitBreaker->setSettings($settings);

        $globalSettings = $circuitBreaker->getSettings();

        $expected = [
            'timeWindow' => $settings['timeWindow'],
            'failureRateThreshold' => CircuitBreaker::FAILURE_RATE_THRESHOLD,
            'intervalToHalfOpen' => CircuitBreaker::INTERVAL_TO_HALF_OPEN,
        ];

        static::assertEquals($expected, $globalSettings);
    }

    public function testIsAvailableWhenIsOpenThenReturnFalse()
    {
        $service = 'service';

        $adapter = $this->createMock(AdapterInterface::class);

        $adapter->method('isOpen')
            ->with($service)
            ->willReturn(true);

        $circuitBreaker = new CircuitBreaker($adapter, $service);

        $isAvailable = $circuitBreaker->isAvailable();

        static::assertFalse($isAvailable);
    }

    public function testIsAvailableWhenReachRateLimitThenReturnFalse()
    {
        $service = 'service';

        $adapter = $this->createMock(AdapterInterface::class);

        $adapter->method('isOpen')
            ->with($service)
            ->willReturn(false);

        $adapter->method('reachRateLimit')
            ->with($service, CircuitBreaker::FAILURE_RATE_THRESHOLD)
            ->willReturn(true);

        $adapter->expects(self::once())
            ->method('setOpenCircuit')
            ->with($service, CircuitBreaker::TIME_WINDOW);

        $adapter->expects(self::once())
            ->method('setHalfOpenCircuit')
            ->with($service, CircuitBreaker::TIME_WINDOW, CircuitBreaker::INTERVAL_TO_HALF_OPEN);

        $circuitBreaker = new CircuitBreaker($adapter, $service);

        $isAvailable = $circuitBreaker->isAvailable();

        static::assertFalse($isAvailable);
    }

    public function testIsAvailableWhenIsNotOpenAndIsNotReachRateLimitThenReturnTrue()
    {
        $service = 'service';

        $adapter = $this->createMock(AdapterInterface::class);

        $adapter->method('isOpen')
            ->with($service)
            ->willReturn(false);

        $adapter->method('reachRateLimit')
            ->with($service, CircuitBreaker::FAILURE_RATE_THRESHOLD)
            ->willReturn(false);

        $circuitBreaker = new CircuitBreaker($adapter, $service);

        $isAvailable = $circuitBreaker->isAvailable();

        static::assertTrue($isAvailable);
    }

    public function testSuccess()
    {
        $service = 'service';

        $adapter = $this->createMock(AdapterInterface::class);

        $adapter->expects(self::once())
            ->method('setSuccess')
            ->with($service);

        $circuitBreaker = new CircuitBreaker($adapter, $service);

        $circuitBreaker->success();
    }
}
