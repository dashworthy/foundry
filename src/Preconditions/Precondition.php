<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Preconditions;

use Closure;
use Dashworthy\Foundry\Data\Data;

/**
 * One rule checked before an Action or a Query runs — shared across both sides,
 * so the same rule can gate a write and the read of the same entity from one
 * class.
 *
 * A precondition is a Laravel Pipeline pipe: `handle()` receives the {@see Data}
 * and the next pipe, calls `$next($data)` to let the chain continue, or throws
 * to refuse. Because it is shared it types on the base `Data`, not one subclass,
 * and narrows inside — with an `instanceof`, or against whatever contract the
 * application layers onto its own Data classes — when it needs a specific shape.
 * Evaluation stops at the first pipe that throws, so order authorization ahead
 * of state checks.
 */
interface Precondition
{
    /**
     * @param  Closure(Data): mixed  $next
     */
    public function handle(Data $data, Closure $next): mixed;
}
