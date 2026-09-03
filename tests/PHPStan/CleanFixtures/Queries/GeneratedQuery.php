<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\PHPStan\CleanFixtures\Queries;

use Dashworthy\Foundry\Preconditions\Precondition;
use Dashworthy\Foundry\Queries\Query;
use Dashworthy\Foundry\Tests\PHPStan\CleanFixtures\Data\GeneratedQueryData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors exactly what `stubs/query.stub` emits for `make:query`: the query and
 * its `{Name}Data` class live in sibling namespaces (`...\Queries` / `...\Data`),
 * so the docblock references below only resolve because of the `use` import
 * above — the same regression guard as GeneratedAction, for the query stub.
 *
 * @extends Query<GeneratedQueryData, Model>
 */
class GeneratedQuery extends Query
{
    /**
     * @return list<Precondition|class-string<Precondition>>
     */
    protected function preconditions(): array
    {
        return [];
    }

    /**
     * @return Builder<Model>
     */
    protected function handle(GeneratedQueryData $data): Builder
    {
        return Model::query();
    }
}
