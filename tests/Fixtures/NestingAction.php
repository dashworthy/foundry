<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Closure;
use Dashworthy\Foundry\Actions\Action;

/**
 * @extends Action<FixtureActionData, string>
 */
class NestingAction extends Action
{
    /**
     * @param  Closure(NestingAction): string|null  $work
     */
    public function __construct(
        private readonly FixtureAction $inner,
        private readonly ?Closure $work = null,
    ) {}

    protected function handle(FixtureActionData $data): string
    {
        $this->inner->execute($data);

        if ($this->work !== null) {
            return ($this->work)($this);
        }

        return 'nested';
    }
}
