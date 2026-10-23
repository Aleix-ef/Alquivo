<?php

namespace App\Domain\Assistant\Services;

/** Elapsed request time must not depend on wall-clock adjustments or business dates. */
class AssistantClock
{
    public function seconds(): float
    {
        return hrtime(true) / 1_000_000_000;
    }
}
