<?php

use LeoCarmo\CircuitBreaker\Adapters\AdapterInterface;
use LeoCarmo\CircuitBreaker\CircuitBreaker;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    public function testGetAdapter()
    {
        $expected = $this->createMock(AdapterInterface::class);
        CircuitBreaker::setAdapter($expected);
        $adapter = CircuitBreaker::getAdapter();
        static::assertEquals($expected, $adapter);
    }

    public function testGetGlobalSettings()
    {
        $settings = ['timeWindow' => 10];
        CircuitBreaker::setGlobalSettings($settings);
        $globalSettings = CircuitBreaker::getGlobalSettings();
        $expected = [
            'timeWindow' => $settings['timeWindow'],
            'failureRateThreshold' => CircuitBreaker::FAILURE_RATE_THRESHOLD,
            'intervalToHalfOpen' => CircuitBreaker::INTERVAL_TO_HALF_OPEN,
        ];
        static::assertEquals($expected, $globalSettings);
    }

    public function testGetServiceSetting()
    {
        $service = 'service-name';
        $setting = 'timeWindow';
        $settings = [$setting => 10];
        CircuitBreaker::setServiceSettings($service, $settings);
        $serviceSetting = CircuitBreaker::getServiceSetting($service, $setting);
        $expected = $settings[$setting];
        static::assertEquals($expected, $serviceSetting);
    }

    public function testIsAvailableWhenIsOpenThenReturnFalse()
    {
        $service = 'service-name';
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('isOpen')
            ->with($service)
            ->willReturn(true);
        CircuitBreaker::setAdapter($adapter);
        $isAvailable = CircuitBreaker::isAvailable($service);
        static::assertFalse($isAvailable);
    }

    public function testIsAvailableWhenReachRateLimitThenReturnFalse()
    {
        $service = 'service-name';
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('isOpen')
            ->with($service)
            ->willReturn(false);
        $adapter->method('reachRateLimit')
            ->with($service)
            ->willReturn(true);
        $adapter->expects(self::once())
            ->method('setOpenCircuit')
            ->with($service);
        $adapter->expects(self::once())
            ->method('setHalfOpenCircuit')
            ->with($service);
        CircuitBreaker::setAdapter($adapter);
        $isAvailable = CircuitBreaker::isAvailable($service);
        static::assertFalse($isAvailable);
    }

    public function testIsAvailableWhenIsNotOpenAndIsNotReachRateLimitThenReturnTrue()
    {
        $service = 'service-name';
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('isOpen')
            ->with($service)
            ->willReturn(false);
        $adapter->method('reachRateLimit')
            ->with($service)
            ->willReturn(false);
        CircuitBreaker::setAdapter($adapter);
        $isAvailable = CircuitBreaker::isAvailable($service);
        static::assertTrue($isAvailable);
    }

    public function testFailureWhenIsHalfOpenThenReturnFalse()
    {
        $service = 'service-name';
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('isHalfOpen')
            ->with($service)
            ->willReturn(true);
        $adapter->expects(self::once())
            ->method('setOpenCircuit')
            ->with($service);
        $adapter->expects(self::once())
            ->method('setHalfOpenCircuit')
            ->with($service);
        CircuitBreaker::setAdapter($adapter);
        $isFailure = CircuitBreaker::failure($service);
        static::assertFalse($isFailure);
    }

    public function testFailureWhenIncrementFailureIsFalseThenReturnFalse()
    {
        $service = 'service-name';
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('isHalfOpen')
            ->with($service)
            ->willReturn(false);
        $adapter->method('incrementFailure')
            ->with($service)
            ->willReturn(false);
        CircuitBreaker::setAdapter($adapter);
        $isFailure = CircuitBreaker::failure($service);
        static::assertFalse($isFailure);
    }

    public function testFailureWhenIncrementFailureIsTrueThenReturnTrue()
    {
        $service = 'service-name';
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('isHalfOpen')
            ->with($service)
            ->willReturn(false);
        $adapter->method('incrementFailure')
            ->with($service)
            ->willReturn(true);
        CircuitBreaker::setAdapter($adapter);
        $isFailure = CircuitBreaker::failure($service);
        static::assertTrue($isFailure);
    }

    public function testSuccess()
    {
        $service = 'service-name';
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects(self::once())
            ->method('setSuccess')
            ->with($service);
        CircuitBreaker::setAdapter($adapter);
        CircuitBreaker::success($service);
    }
}
