<?php

namespace LeoCarmo\CircuitBreaker;

class CircuitBreaker
{

    /**
     * @var \Redis
     */
    protected static $redisClient;

    /**
     * @var string
     */
    protected static $redisNamespace;

    /**
     * @var array
     */
    protected static $cachedService = [];

    /**
     * @var array
     */
    protected static $servicesSettings;

    /**
     * @var array
     */
    protected static $globalSettings;

    /**
     * @var array
     */
    protected static $defaultSettings = [
        'timeWindow' => 60,
        'failureRateThreshold' => 50,
        'intervalToHalfOpen' => 30,
    ];

    /**
     * Set settings for start circuit service
     *
     * @param \Redis $redis
     * @param string $redisNamespace
     */
    public static function setRedisSettings(\Redis $redis, string $redisNamespace)
    {
        self::$redisClient = $redis;
        self::$redisNamespace = $redisNamespace;
    }

    /**
     * Set global settings for all services
     *
     * @param array $settings
     */
    public static function setGlobalSettings(array $settings)
    {
        foreach (self::$defaultSettings as $defaultSetting => $settingValue) {
            self::$globalSettings[$defaultSetting] = $settings[$defaultSetting] ?? $settingValue;
        }
    }

    /**
     * Set custom settings for each service
     *
     * @param string $service
     * @param array $settings
     */
    public static function setServiceSettings(string $service, array $settings)
    {
        $service = self::makeServiceName($service);

        foreach (self::$defaultSettings as $defaultSetting => $settingValue) {
            self::$servicesSettings[$service][$defaultSetting] =
                $settings[$defaultSetting]
                ?? self::$globalSettings[$defaultSetting]
                ?? $settingValue;
        }
    }

    /**
     * Check if circuit is available (closed)
     *
     * @param string $service
     * @return bool
     * @throws \RedisException
     */
    public static function isAvailable(string $service)
    {
        if (self::isOpen($service)) {
            return false;
        }

        if (self::reachRateLimit($service)) {
            self::setAllOpen($service);
            return false;
        }

        return true;
    }

    /**
     * Set new failure for a service
     *
     * @param string $service
     * @return bool|int
     * @throws \RedisException
     */
    public static function failure(string $service)
    {
        if (self::isHalfOpen($service)) {
            self::setAllOpen($service);
            return false;
        }

        $serviceFailures = self::makeServiceName($service) . ':failures';

        if (! self::redis()->get($serviceFailures)) {
            self::redis()->multi();
            self::redis()->incr($serviceFailures);
            self::redis()->expire($serviceFailures, self::getServiceSetting($service, 'timeWindow'));
            return self::redis()->exec()[0] ?? 0;
        }

        return self::redis()->incr($serviceFailures);
    }

    /**
     * Record success and clear all status
     *
     * @param string $service
     * @return bool|int
     * @throws \RedisException
     */
    public static function success(string $service)
    {
        return self::redis()->delete(
            self::redis()->keys(
                self::makeServiceName($service) . ':*'
            )
        );
    }

    /**
     * Internal methods
     */

    /**
     * Return redis client
     *
     * @return \Redis
     * @throws \RedisException
     */
    protected static function redis()
    {
        if (self::$redisClient instanceof \Redis) {
            return self::$redisClient;
        }

        throw new \RedisException('Redis client not defined.');
    }

    /**
     * Get setting for a service, if not set, get from default settings
     *
     * @param string $service
     * @param string $setting
     * @return mixed
     */
    protected static function getServiceSetting(string $service, string $setting)
    {
        $service = self::makeServiceName($service);

        return self::$servicesSettings[$service][$setting]
            ?? self::$globalSettings[$setting]
            ?? self::$defaultSettings[$setting];
    }

    /**
     * @param string $service
     * @return string
     */
    protected static function makeServiceName(string $service)
    {
        if (isset(self::$cachedService[$service])) {
            return self::$cachedService[$service];
        }

        return self::$cachedService[$service] = 'circuit-breaker:' . self::$redisNamespace . ':' . base64_encode($service);
    }

    /**
     * @param string $service
     * @throws \RedisException
     */
    protected static function setOpenCircuit(string $service)
    {
        self::redis()->set(
            self::makeServiceName($service) . ':open',
            time(),
            self::getServiceSetting($service, 'timeWindow')
        );
    }

    /**
     * @param string $service
     * @throws \RedisException
     */
    protected static function setHalfOpenCircuit(string $service)
    {
        self::redis()->set(
            self::makeServiceName($service) . ':half_open',
            time(),
            self::getServiceSetting($service, 'timeWindow') + self::getServiceSetting($service, 'intervalToHalfOpen')
        );
    }

    /**
     * @param string $service
     * @throws \RedisException
     */
    protected static function setAllOpen(string $service)
    {
        self::redis()->multi();
        self::setOpenCircuit($service);
        self::setHalfOpenCircuit($service);
        self::redis()->exec();
    }

    /**
     * @param string $service
     * @return bool
     * @throws \RedisException
     */
    protected static function isOpen(string $service)
    {
        return self::redis()->get(self::makeServiceName($service) . ':open');
    }

    /**
     * @param string $service
     * @return bool
     * @throws \RedisException
     */
    protected static function isHalfOpen(string $service)
    {
        return self::redis()->get(self::makeServiceName($service) . ':half_open');
    }

    /**
     * @param string $service
     * @return bool
     * @throws \RedisException
     */
    protected static function reachRateLimit(string $service)
    {
        $failures = self::redis()->get(
            self::makeServiceName($service) . ':failures'
        );

        return $failures && $failures >= self::getServiceSetting($service, 'failureRateThreshold');
    }
}
