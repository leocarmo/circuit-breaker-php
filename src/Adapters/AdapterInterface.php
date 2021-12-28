<?php declare(strict_types=1);

namespace LeoCarmo\CircuitBreaker\Adapters;

interface AdapterInterface
{

    public function isOpen(string $service): bool;

    public function reachRateLimit(string $service, int $failureRateThreshold): bool;

    public function setOpenCircuit(string $service, int $timeWindow): void;

    public function setHalfOpenCircuit(string $service, int $timeWindow, int $intervalToHalfOpen): void;

    public function isHalfOpen(string $service): bool;

    public function incrementFailure(string $service, int $timeWindow) : bool;

    public function setSuccess(string $service): void;

    public function getFailuresCounter(string $service): int;

}