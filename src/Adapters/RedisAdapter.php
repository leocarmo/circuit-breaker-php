<?php

namespace LeoCarmo\CircuitBreaker\Adapters;

use Redis;
use LeoCarmo\CircuitBreaker\CircuitBreaker;

class RedisAdapter implements AdapterInterface
{

    /**
     * @var Redis
     */
    protected Redis $redis;

    /**
     * @var string
     */
    protected string $redisNamespace;

    /**
     * @var array
     */
    protected array $cachedService = [];

    /**
     * Set settings for start circuit service
     *
     * @param Redis $redis
     * @param string $redisNamespace
     */
    public function __construct(Redis $redis, string $redisNamespace)
    {
        $this->redis = $redis;
        $this->redisNamespace = $redisNamespace;
    }

    /**
     * @param string $service
     * @return bool|string
     */
    public function isOpen(string $service): bool
    {
        return $this->redis->get($this->makeNamespace($service) . ':open');
    }

    /**
     * @param string $service
     * @return bool
     */
    public function reachRateLimit(string $service): bool
    {
        $failures = (int) $this->redis->get(
            $this->makeNamespace($service) . ':failures'
        );

        return ($failures >= CircuitBreaker::getServiceSetting($service, 'failureRateThreshold'));
    }

    /**
     * @param string $service
     * @return bool|string
     */
    public function isHalfOpen(string $service): bool
    {
        return (bool) $this->redis->get($this->makeNamespace($service) . ':half_open');
    }

    /**
     * @param string $service
     * @return bool
     */
    public function incrementFailure(string $service) : bool
    {
        $serviceName = self::makeNamespace($service) . ':failures';

        if (! $this->redis->get($serviceName)) {
            $this->redis->multi();
            $this->redis->incr($serviceName);
            $this->redis->expire($serviceName, CircuitBreaker::getServiceSetting($service, 'timeWindow'));
            return (bool) ($this->redis->exec()[0] ?? false);
        }

        return (bool) $this->redis->incr($serviceName);
    }

    /**
     * @param string $service
     */
    public function setSuccess(string $service): void
    {
        $serviceName = $this->makeNamespace($service);

        $this->redis->multi();
        $this->redis->del($serviceName . ':open');
        $this->redis->del($serviceName . ':failures');
        $this->redis->del($serviceName . ':half_open');
        $this->redis->exec();
    }

    /**
     * @param string $service
     */
    public function setOpenCircuit(string $service): void
    {
        $this->redis->set(
            $this->makeNamespace($service) . ':open',
            time(),
            CircuitBreaker::getServiceSetting($service, 'timeWindow')
        );
    }

    /**
     * @param string $service
     */
    public function setHalfOpenCircuit(string $service): void
    {
        $this->redis->set(
            $this->makeNamespace($service) . ':half_open',
            time(),
            CircuitBreaker::getServiceSetting($service, 'timeWindow')
            + CircuitBreaker::getServiceSetting($service, 'intervalToHalfOpen')
        );
    }

    /**
     * @param string $service
     * @return string
     */
    protected function makeNamespace(string $service): string
    {
        if (isset($this->cachedService[$service])) {
            return $this->cachedService[$service];
        }

        return $this->cachedService[$service] = 'circuit-breaker:' . $this->redisNamespace . ':' . base64_encode($service);
    }
}
