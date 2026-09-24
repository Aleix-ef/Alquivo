<?php

namespace App\Domain\Assistant\Contracts;

/**
 * Application-owned generation contract: messages, closed function tools and a reply schema.
 * Adapters normalize output to message/function_call items plus usage, model, id and status.
 * Providers cannot resolve identities, query domain models or execute tools.
 */
interface AIProviderInterface
{
    public function generate(array $request, float $timeout): array;
}
