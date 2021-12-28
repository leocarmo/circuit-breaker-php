<?php declare(strict_types=1);

namespace LeoCarmo\CircuitBreaker;

use LeoCarmo\CircuitBreaker\Adapters\AdapterInterface;

class CircuitBreaker
{
    public const TIME_WINDOW = 60;
    public const FAILURE_RATE_THRESHOLD = 50;
    public const INTERVAL_TO_HALF_OPEN = 30;

    /**
     * @var AdapterInterface
     */
    protected AdapterInterface $adapter;

    /**
     * @var string
     */
    protected string $service;

    /**
     * @var array
     */
    protected array $settings = [];

    /**
     * @var array
     */
    protected array $defaultSettings = [
        'timeWindow' => self::TIME_WINDOW,
        'failureRateThreshold' => self::FAILURE_RATE_THRESHOLD,
        'intervalToHalfOpen' => self::INTERVAL_TO_HALF_OPEN,
    ];

    /**
     * @param AdapterInterface $adapter
     * @param string $service
     */
    public function __construct(AdapterInterface $adapter, string $service)
    {
        $this->adapter = $adapter;
        $this->service = $service;
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @return string
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * Set global settings for all services
     *
     * @param array $settings
     * @return void
     */
    public function setSettings(array $settings): void
    {
        foreach ($this->defaultSettings as $defaultSettingKey => $defaultSettingValue) {
            $this->settings[$defaultSettingKey] = (int) ($settings[$defaultSettingKey] ?? $defaultSettingValue);
        }
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getSetting(string $name)
    {
        return $this->settings[$name] ?? $this->defaultSettings[$name];
    }

    /**
     * Check if circuit is available (closed)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        if ($this->adapter->isOpen($this->service)) {
            return false;
        }

        $reachRateLimit = $this->adapter->reachRateLimit(
            $this->service,
            $this->getSetting('failureRateThreshold')
        );

        if ($reachRateLimit) {
            $this->openCircuit();
            return false;
        }

        return true;
    }

    /**
     * Set new failure for a service
     *
     * @return void
     */
    public function failure(): void
    {
        $isHalfOpen = $this->adapter->isHalfOpen($this->service);

        if ($isHalfOpen) {
            $this->openCircuit();
            return;
        }

        $this->adapter->incrementFailure(
            $this->service,
            $this->getSetting('timeWindow')
        );
    }

    /**
     * Record success and clear all status
     *
     * @return void
     */
    public function success(): void
    {
        $this->adapter->setSuccess($this->service);
    }

    /**
     * Open circuit
     */
    public function openCircuit(): void
    {
        $this->adapter->setOpenCircuit(
            $this->service,
            $this->getSetting('timeWindow')
        );
        $this->adapter->setHalfOpenCircuit(
            $this->service,
            $this->getSetting('timeWindow'),
            $this->getSetting('intervalToHalfOpen')
        );
    }

    public function getFailuresCounter(): int
    {
        return $this->adapter->getFailuresCounter($this->service);
    }
}
