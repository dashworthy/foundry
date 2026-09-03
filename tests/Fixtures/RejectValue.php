<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Closure;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Preconditions\Precondition;
use RuntimeException;

/**
 * A precondition pipe that refuses a {@see FixtureQueryData} carrying the
 * sentinel value `refuse`, so tests can prove preconditions gate `handle()`.
 */
final class RejectValue implements Precondition
{
    public function handle(Data $data, Closure $next): mixed
    {
        if ($data instanceof FixtureQueryData && $data->value === 'refuse') {
            throw new RuntimeException('Refused by precondition.');
        }

        return $next($data);
    }
}
