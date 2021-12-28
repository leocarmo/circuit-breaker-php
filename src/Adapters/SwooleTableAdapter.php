<?php declare(strict_types=1);

namespace LeoCarmo\CircuitBreaker\Adapters;

use Swoole\Table;

class SwooleTableAdapter implements AdapterInterface
{
    protected Table $table;

    public function __construct()
    {
        $this->checkExtensionLoaded();
        $this->table = $this->createTable();
    }

    protected function createTable()
    {
        $table = new Table(12);
        $table->column('count', Table::TYPE_INT, 4);
        $table->column('until_date', Table::TYPE_INT, 4);
        $table->create();
        return $table;
    }

    protected function checkExtensionLoaded()
    {
        if (! extension_loaded('swoole')) {
            throw new \RuntimeException('Extension swoole is required to use SwooleTableAdapter.');
        }
    }

    /**
     * @param string $service
     * @param int $timeWindow
     * @return bool
     */
    public function incrementFailure(string $service, int $timeWindow): bool
    {
        $key = "{$service}-failures";

        if ($this->table->exist($key)) {
            return is_integer($this->table->incr($key, 'count'));
        }

        $date = (new \DateTime("+{$timeWindow} seconds"));

        return $this->table->set($key, [
            'count' => 1,
            'until_date' => $date->getTimestamp(),
        ]);
    }

    public function reachRateLimit(string $service, int $failureRateThreshold): bool
    {
        $key = "{$service}-failures";

        if (! $this->table->exist($key)) {
            return false;
        }

        $failures = $this->table->get($key);

        if ((new \DateTime('now'))->getTimestamp() > $failures['until_date']) {
            $this->table->delete("{$service}-failures");
            return false;
        }

        return ($failures['count'] >= $failureRateThreshold);
    }

    /**
     * @param string $service
     */
    public function setSuccess(string $service): void
    {
        $this->table->delete("{$service}-failures");
        $this->table->delete("{$service}-open");
        $this->table->delete("{$service}-half_open");
    }

    /**
     * @param string $service
     * @return bool
     */
    public function isOpen(string $service): bool
    {
        $key = "{$service}-open";

        if (! $this->table->exists($key)) {
            return false;
        }

        $open = $this->table->get($key, 'until_date');

        return (
            (new \DateTime('now'))->getTimestamp() < $open
        );
    }

    /**
     * @param string $service
     * @return bool|string
     */
    public function isHalfOpen(string $service): bool
    {
        $key = "{$service}-half_open";

        if (! $this->table->exists($key)) {
            return false;
        }

        $halfOpen = $this->table->get($key, 'until_date');

        return (
            (new \DateTime('now'))->getTimestamp() < $halfOpen
        );
    }

    /**
     * @param string $service
     * @param int $timeWindow
     */
    public function setOpenCircuit(string $service, int $timeWindow): void
    {
        $date = (new \DateTime("+{$timeWindow} seconds"));

        $this->table->set(
            "{$service}-open",
            [
                'until_date' => $date->getTimestamp(),
            ]
        );

        $this->table->delete("{$service}-failures");
    }

    /**
     * @param string $service
     * @param int $timeWindow
     * @param int $intervalToHalfOpen
     */
    public function setHalfOpenCircuit(string $service, int $timeWindow, int $intervalToHalfOpen): void
    {
        $seconds = ($timeWindow + $intervalToHalfOpen);

        $date = (new \DateTime("+{$seconds} seconds"));

        $this->table->set(
            "{$service}-half_open",
            [
                'until_date' => $date->getTimestamp(),
            ]
        );

        $this->table->delete("{$service}-failures");
    }

    public function getFailuresCounter(string $service): int
    {
        $failures = $this->table->get("{$service}-failures");

        return (int) ($failures['count'] ?? 0);
    }
}
