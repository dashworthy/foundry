<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Closure;
use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Preconditions\Precondition;

/**
 * Not `final` — subclassing or mocking in tests needs to reach in without
 * fighting the class declaration. Same rule as real actions.
 *
 * @extends Action<FixtureActionData, string>
 */
class FixtureAction extends Action
{
    public int $handled = 0;

    public int $rolledBack = 0;

    /**
     * @param  list<Precondition|class-string<Precondition>>  $preconditions
     * @param  Closure(FixtureAction): string|null  $work
     * @param  Closure(FixtureAction): void|null  $onRollback
     */
    public function __construct(
        private readonly array $preconditions = [],
        private readonly ?Closure $work = null,
        private readonly ?Closure $onRollback = null,
    ) {}

    /**
     * @return list<Precondition|class-string<Precondition>>
     */
    protected function preconditions(): array
    {
        return $this->preconditions;
    }

    protected function handle(FixtureActionData $data): string
    {
        $this->handled++;

        if ($this->work !== null) {
            return ($this->work)($this);
        }

        return $data->name;
    }

    protected function rollback(): void
    {
        $this->rolledBack++;

        if ($this->onRollback !== null) {
            ($this->onRollback)($this);
        }
    }
}
