<?php

namespace LeoCarmo\CircuitBreaker;

use LeoCarmo\CircuitBreaker\Adapters\AdapterInterface;

class CircuitBreaker
{

    /**
     * @var AdapterInterface
     */
    protected static $adapter;

    /**
     * @var string
     */
    protected static $redisNamespace;

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
     * @param AdapterInterface $adapter
     */
    public static function setAdapter(AdapterInterface $adapter) : void
    {
        self::$adapter = $adapter;
    }

    /**
     * @return AdapterInterface
     */
    public static function getAdapter() : AdapterInterface
    {
        return self::$adapter;
    }

    /**
     * Set global settings for all services
     *
     * @param array $settings
     */
    public static function setGlobalSettings(array $settings) : void
    {
        foreach (self::$defaultSettings as $defaultSetting => $settingValue) {
            self::$globalSettings[$defaultSetting] = (int) $settings[$defaultSetting] ?? $settingValue;
        }
    }

    /**
     * @return array
     */
    public static function getGlobalSettings() : array
    {
        return self::$globalSettings;
    }

    /**
     * Set custom settings for each service
     *
     * @param string $service
     * @param array $settings
     */
    public static function setServiceSettings(string $service, array $settings) : void
    {
        foreach (self::$defaultSettings as $defaultSetting => $settingValue) {
            self::$servicesSettings[$service][$defaultSetting] =
                (int) $settings[$defaultSetting]
                ?? self::$globalSettings[$defaultSetting]
                ?? $settingValue;
        }
    }

    /**
     * Get setting for a service, if not set, get from default settings
     *
     * @param string $service
     * @param string $setting
     * @return mixed
     */
    public static function getServiceSetting(string $service, string $setting)
    {
        return self::$servicesSettings[$service][$setting]
            ?? self::$globalSettings[$setting]
            ?? self::$defaultSettings[$setting];
    }

    /**
     * Check if circuit is available (closed)
     *
     * @param string $service
     * @return bool
     */
    public static function isAvailable(string $service) : bool
    {
        if (self::$adapter->isOpen($service)) {
            return false;
        }

        if (self::$adapter->reachRateLimit($service)) {
            self::$adapter->setOpenCircuit($service);
            self::$adapter->setHalfOpenCircuit($service);
            return false;
        }

        return true;
    }

    /**
     * Set new failure for a service
     *
     * @param string $service
     * @return bool
     */
    public static function failure(string $service)
    {
        if (self::$adapter->isHalfOpen($service)) {
            self::$adapter->setOpenCircuit($service);
            self::$adapter->setHalfOpenCircuit($service);
            return false;
        }

        return self::$adapter->incrementFailure($service);
    }

    /**
     * Record success and clear all status
     *
     * @param string $service
     * @return bool|int
     */
    public static function success(string $service)
    {
        return self::$adapter->setSuccess($service);
    }
}
