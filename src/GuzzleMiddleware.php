<?php declare(strict_types=1);

namespace LeoCarmo\CircuitBreaker;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleMiddleware
{
    private const DEFAULT_SUCCESS_FAMILY = 200;

    private const DEFAULT_IGNORE_FAMILY = 300;

    protected CircuitBreaker $circuitBreaker;

    protected array $customSuccessCodes = [];

    protected array $customIgnoreCodes = [];

    protected array $customSuccessFamily = [];

    protected array $customIgnoreFamily = [];

    public function __construct(CircuitBreaker $circuitBreaker)
    {
        $this->circuitBreaker = $circuitBreaker;
        $this->setCustomSuccessFamily(self::DEFAULT_SUCCESS_FAMILY);
        $this->setCustomIgnoreFamily(self::DEFAULT_IGNORE_FAMILY);
    }

    public function setCustomSuccessCodes(array $codes): void
    {
        $this->customSuccessCodes = $codes;
    }

    private function validateCodeStatusFamily(int $family): void
    {
        $num_length = strlen((string) $family);
        $initial_number = substr((string) $family, 0, 1);
        if (($num_length !== 3) && ($initial_number > 0 && $initial_number < 6)) {
            throw new \InvalidArgumentException('This code status family is not valid.');
        }
    }

    public function setCustomSuccessFamily(int $family): void
    {
        $this->validateCodeStatusFamily($family);
        $initial_number = substr((string) $family, 0, 1);
        $this->customSuccessFamily[] = $initial_number;
    }

    public function setCustomIgnoreFamily(int $family): void
    {
        $this->validateCodeStatusFamily($family);
        $initial_number = substr((string) $family, 0, 1);
        $this->customIgnoreFamily[] = $initial_number;
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

        if ($this->isIgnoredStatus($statusCode)) {
            return;
        }

        if (! $this->isStatusCodeRangeValid($statusCode)) {
            $this->circuitBreaker->failure();
            return;
        }

        if ($this->isStatusCodeSuccess($statusCode)) {
            $this->circuitBreaker->success();
            return;
        }

        $this->circuitBreaker->failure();
    }

    protected function isStatusCodeFamilyToIgnore(int $statusCode): bool
    {
        $initial_number = substr((string) $statusCode, 0, 1);
        return (in_array($initial_number, $this->customIgnoreFamily));
    }

    protected  function isIgnoredStatus(int $statusCode): bool
    {
        return $this->isStatusCodeFamilyToIgnore($statusCode) || in_array($statusCode, $this->customIgnoreCodes);
    }

    protected function isStatusCodeRangeValid(int $statusCode): bool
    {
        return ($statusCode >= 100 && $statusCode < 600);
    }

    protected function isStatusCodeFamilyToSuccess(int $statusCode): bool
    {
        $initial_number = substr((string) $statusCode, 0, 1);
        return (in_array($initial_number, $this->customSuccessFamily));
    }

    protected function isStatusCodeSuccess(int $statusCode): bool
    {
        return ($this->isStatusCodeFamilyToSuccess($statusCode) || in_array($statusCode, $this->customSuccessCodes));
    }
}