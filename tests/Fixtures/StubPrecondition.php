<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Closure;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Preconditions\Precondition;
use Throwable;

/**
 * A precondition pipe for tests: passes through to $next by default, or
 * throws a configured exception to simulate a refusal — standing in for
 * whatever exception an application's own precondition would throw.
 */
final class StubPrecondition implements Precondition
{
    public int $evaluations = 0;

    public function __construct(private readonly ?Throwable $refusal = null) {}

    public function handle(Data $data, Closure $next): mixed
    {
        $this->evaluations++;

        if ($this->refusal !== null) {
            throw $this->refusal;
        }

        return $next($data);
    }
}
