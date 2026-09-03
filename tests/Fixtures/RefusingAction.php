<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Preconditions\Precondition;
use RuntimeException;

/**
 * An action that refuses and does real work, both declared on the class rather
 * than injected.
 *
 * `FixtureAction` takes its preconditions through the constructor, which makes
 * it useless for testing `fake()`: Mockery builds the partial without calling
 * the constructor, so the mock has no preconditions whatever `fake()` does, and
 * a test using it would pass even if the stubbing were removed. Declaring them
 * on the class means the partial mock inherits the refusal for real, so
 * stubbing it is the only thing that can make execute() succeed.
 *
 * @extends Action<FixtureActionData, string>
 */
class RefusingAction extends Action
{
    public int $handled = 0;

    /**
     * @return list<Precondition|class-string<Precondition>>
     */
    protected function preconditions(): array
    {
        return [new StubPrecondition(new RuntimeException('refused'))];
    }

    protected function handle(FixtureActionData $data): string
    {
        $this->handled++;

        return 'worked';
    }
}
