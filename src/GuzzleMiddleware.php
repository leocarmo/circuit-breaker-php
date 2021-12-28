<?php declare(strict_types=1);

namespace LeoCarmo\CircuitBreaker;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleMiddleware
{
    protected CircuitBreaker $circuitBreaker;

    protected array $customSuccessCodes = [];

    protected array $customIgnoreCodes = [];

    public function __construct(CircuitBreaker $circuitBreaker)
    {
        $this->circuitBreaker = $circuitBreaker;
    }

    public function setCustomSuccessCodes(array $codes): void
    {
        $this->customSuccessCodes = $codes;
    }

    public function setCustomIgnoreCodes(array $codes): void
    {
        $this->customIgnoreCodes = $codes;
    }

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {

            if (! $this->circuitBreaker->isAvailable()) {
                throw new CircuitBreakerException(
                    sprintf('"%s" is not available', $this->circuitBreaker->getService())
                );
            }

            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) {
                    $this->executeCircuitBreakerOnResponse($response);

                    return $response;
                },
                function (\Throwable $exception) {
                    $this->circuitBreaker->failure();
                    throw $exception;
                }
            );
        };
    }

    protected function executeCircuitBreakerOnResponse(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if (in_array($statusCode, $this->customIgnoreCodes)) {
            return;
        }

        if (! $this->isStatusCodeRangeValid($statusCode)) {
            $this->circuitBreaker->failure();
            return;
        }

        if ($this->isStatusCodeRedirect($statusCode)) {
            return;
        }

        if ($this->isStatusCodeSuccess($statusCode) || in_array($statusCode, $this->customSuccessCodes)) {
            $this->circuitBreaker->success();
            return;
        }

        $this->circuitBreaker->failure();
    }

    protected function isStatusCodeRangeValid(int $statusCode): bool
    {
        return ($statusCode >= 100 && $statusCode < 600);
    }

    protected function isStatusCodeRedirect(int $statusCode): bool
    {
        return ($statusCode >= 300 && $statusCode < 400);
    }

    protected function isStatusCodeSuccess(int $statusCode): bool
    {
        return ($statusCode >= 200 && $statusCode < 300);
    }
}