<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Concerns;

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Preconditions\Precondition;
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Support\Facades\Pipeline;
use Throwable;

/**
 * The precondition pipeline shared by {@see Action}
 * and {@see Query}. A precondition is a rule checked
 * before the work runs, and the same rule gates a write and the read of the same
 * entity — so both sides declare, evaluate, and gate on preconditions
 * identically, and that behaviour lives here once.
 *
 * @template TData of Data
 */
trait EvaluatesPreconditions
{
    /**
     * Whether assert() runs the preconditions. A gate is the baseline for every
     * unit; a call site drops it with withoutPreconditions().
     */
    private bool $evaluatesPreconditions = true;

    /**
     * Preconditions in evaluation order. Order them cheap first: evaluation
     * stops at the first that throws, so an authorization check placed ahead of
     * a state check means an unauthorized actor never learns the state.
     *
     * Each pipe is a {@see Precondition} — `handle(Data $data, Closure $next): mixed`.
     * Return either instantiated pipe objects or class-strings; the Pipeline
     * resolves class-strings from the container.
     *
     * @return list<Precondition|class-string<Precondition>>
     */
    protected function preconditions(): array
    {
        return [];
    }

    /**
     * Skip the preconditions for this one call, then run as usual:
     * `SomeAction::make()->withoutPreconditions()->execute($data)`. Like
     * withoutTransaction(), the opt-out lives on the call, not the class.
     *
     * Reach for this almost never. Preconditions are where a unit's
     * authorization and state rules live, so bypassing them runs the work for an
     * actor the rules would have refused. It exists only for a trusted caller
     * that has already enforced those rules itself — a data migration, a seeder,
     * a maintenance command running as no one — never for application request
     * flow, where skipping the gate is a security bug.
     */
    public function withoutPreconditions(): static
    {
        $this->evaluatesPreconditions = false;

        return $this;
    }

    /**
     * Runs every precondition in order, stopping at the first that throws.
     * Whatever exception a precondition throws propagates unchanged — this
     * package neither wraps nor replaces it. A no-op when withoutPreconditions()
     * has turned the gate off.
     *
     * @param  TData  $data
     */
    public function assert(Data $data): void
    {
        if (! $this->evaluatesPreconditions) {
            return;
        }

        Pipeline::send($data)->through($this->preconditions())->thenReturn();
    }

    /**
     * Whether this unit would be permitted — for deciding if a control renders,
     * so the UI and the server cannot drift. Reports `true` unconditionally when
     * withoutPreconditions() has turned the gate off, since there is then no rule
     * left to refuse.
     *
     * @param  TData  $data
     */
    public function permits(Data $data): bool
    {
        try {
            $this->assert($data);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
