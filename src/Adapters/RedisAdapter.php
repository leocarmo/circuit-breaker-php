<?php declare(strict_types=1);

namespace LeoCarmo\CircuitBreaker\Adapters;

class RedisAdapter implements AdapterInterface
{
    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected string $redisNamespace;

    /**
     * Set settings for start circuit service
     *
     * @param $redis
     * @param string $redisNamespace
     */
    public function __construct($redis, string $redisNamespace)
    {
        $this->checkExtensionLoaded();
        $this->redis = $redis;
        $this->redisNamespace = $redisNamespace;
    }

    protected function checkExtensionLoaded()
    {
        if (! extension_loaded('redis')) {
            throw new \RuntimeException('Extension redis is required to use RedisAdapter.');
        }
    }

    /**
     * @param string $service
     * @return bool
     */
    public function isOpen(string $service): bool
    {
        return (bool) $this->redis->get(
            $this->makeNamespace($service) . ':open'
        );
    }

    /**
     * @param string $service
     * @param int $failureRateThreshold
     * @return bool
     */
    public function reachRateLimit(string $service, int $failureRateThreshold): bool
    {
        $failures = (int) $this->redis->get(
            $this->makeNamespace($service) . ':failures'
        );

        return ($failures >= $failureRateThreshold);
    }

    /**
     * @param string $service
     * @return bool|string
     */
    public function isHalfOpen(string $service): bool
    {
        return (bool) $this->redis->get(
            $this->makeNamespace($service) . ':half_open'
        );
    }

    /**
     * @param string $service
     * @param int $timeWindow
     * @return bool
     */
    public function incrementFailure(string $service, int $timeWindow) : bool
    {
        $serviceName = $this->makeNamespace($service) . ':failures';

        if (! $this->redis->get($serviceName)) {
            $this->redis->multi();
            $this->redis->incr($serviceName);
            $this->redis->expire($serviceName, $timeWindow);
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
     * @param int $timeWindow
     */
    public function setOpenCircuit(string $service, int $timeWindow): void
    {
        $this->redis->set(
            $this->makeNamespace($service) . ':open',
            time(),
            $timeWindow
        );
    }

    /**
     * @param string $service
     * @param int $timeWindow
     * @param int $intervalToHalfOpen
     */
    public function setHalfOpenCircuit(string $service, int $timeWindow, int $intervalToHalfOpen): void
    {
        $this->redis->set(
            $this->makeNamespace($service) . ':half_open',
            time(),
            ($timeWindow + $intervalToHalfOpen)
        );
    }

    public function getFailuresCounter(string $service): int
    {
        $failures = $this->redis->get(
            $this->makeNamespace($service) . ':failures'
        );

        return (int) $failures;
    }

    /**
     * @param string $service
     * @return string
     */
    protected function makeNamespace(string $service): string
    {
        return 'circuit-breaker:' . $this->redisNamespace . ':' . $service;
    }
}
