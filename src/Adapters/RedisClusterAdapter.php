<?php declare(strict_types=1);

namespace LeoCarmo\CircuitBreaker\Adapters;

class RedisClusterAdapter extends RedisAdapter
{
    /**
     * @param string $service
     * @param int $timeWindow
     * @return bool
     */
    public function incrementFailure(string $service, int $timeWindow): bool
    {
        $serviceName = $this->makeNamespace($service) . ':failures';

        if (! $this->redis->get($serviceName)) {
            $this->redis->incr($serviceName);
            return (bool) $this->redis->expire($serviceName, $timeWindow);
        }

        return (bool) $this->redis->incr($serviceName);
    }

    /**
     * @param string $service
     */
    public function setSuccess(string $service): void
    {
        $serviceName = $this->makeNamespace($service);

        $this->redis->del($serviceName . ':open');
        $this->redis->del($serviceName . ':failures');
        $this->redis->del($serviceName . ':half_open');
    }
}
