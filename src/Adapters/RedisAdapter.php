<?php

namespace LeoCarmo\CircuitBreaker\Adapters;

use LeoCarmo\CircuitBreaker\CircuitBreaker;

class RedisAdapter implements AdapterInterface
{

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $redisNamespace;

    /**
     * @var array
     */
    protected $cachedService = [];

    /**
     * Set settings for start circuit service
     *
     * @param \Redis $redis
     * @param string $redisNamespace
     */
    public function __construct(\Redis $redis, string $redisNamespace)
    {
        $this->redis = $redis;
        $this->redisNamespace = $redisNamespace;
    }

    /**
     * @param string $service
     * @return bool|string
     */
    public function isOpen(string $service) : bool
    {
        return $this->redis->get($this->makeNamespace($service) . ':open');
    }

    /**
     * @param string $service
     * @return bool
     */
    public function reachRateLimit(string $service) : bool
    {
        $failures = $this->redis->get(
            $this->makeNamespace($service) . ':failures'
        );

        return $failures && $failures >= CircuitBreaker::getServiceSetting($service, 'failureRateThreshold');
    }

    /**
     * @param string $service
     * @return bool|string
     */
    public function isHalfOpen(string $service) : bool
    {
        return (bool) $this->redis->get($this->makeNamespace($service) . ':half_open');
    }

    /**
     * @param string $service
     * @return bool
     */
    public function incrementFailure(string $service) : bool
    {
        $serviceFailures = self::makeNamespace($service) . ':failures';

        if (! $this->redis->get($serviceFailures)) {
            $this->redis->multi();
            $this->redis->incr($serviceFailures);
            $this->redis->expire($serviceFailures, CircuitBreaker::getServiceSetting($service, 'timeWindow'));
            return (bool) $this->redis->exec()[0] ?? false;
        }

        return (bool) $this->redis->incr($serviceFailures);
    }

    /**
     * @param string $service
     * @return bool
     */
    public function setSuccess(string $service) : bool
    {
        return (bool) $this->redis->delete(
            $this->redis->keys(
                $this->makeNamespace($service) . ':*'
            )
        );
    }

    /**
     * @param string $service
     */
    public function setOpenCircuit(string $service) : void
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
    public function setHalfOpenCircuit(string $service) : void
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
    protected function makeNamespace(string $service)
    {
        if (isset($this->cachedService[$service])) {
            return $this->cachedService[$service];
        }

        return $this->cachedService[$service] = 'circuit-breaker:' . $this->redisNamespace . ':' . base64_encode($service);
    }
}
