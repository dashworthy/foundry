<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Dashworthy\Foundry\Preconditions\Precondition;
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Query<FixtureQueryData, Widget>
 */
class FixtureQuery extends Query
{
    public int $handleCalls = 0;

    /**
     * Build the read — filter widgets by name. The base class runs `->get()`;
     * this only returns the unexecuted builder, so the tests can assert that the
     * abstract base, not the concrete query, is what materialises the rows.
     *
     * @return Builder<Widget>
     */
    protected function handle(FixtureQueryData $data): Builder
    {
        $this->handleCalls++;

        return Widget::query()->where('name', $data->value);
    }

    /**
     * @return list<Precondition|class-string<Precondition>>
     */
    protected function preconditions(): array
    {
        return [new RejectValue];
    }
}
