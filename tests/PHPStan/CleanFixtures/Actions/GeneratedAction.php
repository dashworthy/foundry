<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\PHPStan\CleanFixtures\Actions;

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Preconditions\Precondition;
use Dashworthy\Foundry\Tests\PHPStan\CleanFixtures\Data\GeneratedActionData;

/**
 * Mirrors exactly what `stubs/action.stub` emits for `make:action`: the action
 * and its `{Name}Data` class live in sibling namespaces (`...\Actions` /
 * `...\Data`), so the docblock references below only resolve because of the
 * `use` import above. This is the regression fixture for the bug where that
 * import was missing and every generated action failed PHPStan with an
 * unresolvable `{{ class }}Data`.
 *
 * @extends Action<GeneratedActionData, null>
 */
class GeneratedAction extends Action
{
    /**
     * @return list<Precondition|class-string<Precondition>>
     */
    protected function preconditions(): array
    {
        return [];
    }

    protected function handle(GeneratedActionData $data): mixed
    {
        return null;
    }
}
