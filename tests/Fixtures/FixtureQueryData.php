<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Dashworthy\Foundry\Data\Data;

final readonly class FixtureQueryData extends Data
{
    public function __construct(public string $value = 'ok') {}
}
