<?php

namespace App\Domain\Assistant\Providers;

use App\Domain\Assistant\Contracts\AIProviderInterface;
use RuntimeException;
use Throwable;

final class FakeAIProvider implements AIProviderInterface
{
    public array $requests = [];

    public function __construct(private array $responses = []) {}

    public function push(array|Throwable|\Closure $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function generate(array $request, float $timeout): array
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses) ?? throw new RuntimeException('No fake AI response queued.');
        if ($response instanceof \Closure) {
            $response = $response($request);
        }
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}
