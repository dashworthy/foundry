<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Actions;

use Dashworthy\Foundry\Concerns\EvaluatesPreconditions;
use Dashworthy\Foundry\Concerns\InteractsWithContainer;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A write expressed as one class: it takes exactly one {@see Data} object, runs
 * its preconditions, does the work inside a database transaction, and returns
 * whatever the work produced. Its read-side twin is {@see Query}.
 *
 * The container lifecycle (`make()`, `fake()`) and the precondition pipeline
 * (`preconditions()`, `assert()`, `permits()`, `withoutPreconditions()`) are
 * identical on both sides and live in {@see InteractsWithContainer} and
 * {@see EvaluatesPreconditions}. What is unique to the write side stays here:
 * the transaction, `withoutTransaction()`, and `rollback()`.
 *
 * `handle()` is declared here as `@method` rather than as a real abstract
 * method so a concrete action can typehint its own `{Name}Data` —
 * `handle(PublishPostData $data)`, not `handle(Data $data)`. PHP forbids
 * narrowing a parameter type in an override, so an abstract `handle(Data $data)`
 * here would fatal every subclass that named its real data class, forcing all of
 * them to widen to the base type and restate the true type in a docblock. The
 * annotation keeps the narrow type in the signature, where a wrong argument is a
 * `TypeError` at the boundary instead of an undefined-property error midway
 * through the work.
 *
 * The trade is that a subclass missing `handle()` no longer fatals at
 * class-declaration time; it fails on first `execute()`. Applications should
 * buy that back with an architecture test asserting every `Action` subclass
 * declares `handle()`.
 *
 * @template TData of Data
 * @template TReturn
 *
 * @method TReturn handle(TData $data) Do the work. Runs inside the transaction execute() owns.
 */
abstract class Action
{
    /** @use EvaluatesPreconditions<TData> */
    use EvaluatesPreconditions;

    use InteractsWithContainer;

    /**
     * Whether execute() wraps handle() in a database transaction. A transaction
     * is the baseline for every action; a call site drops it with
     * withoutTransaction().
     */
    private bool $withinTransaction = true;

    /**
     * Skip the database transaction for this one call, then execute as usual:
     * `SomeAction::make()->withoutTransaction()->execute($data)`.
     *
     * A transaction is the baseline, so most actions never touch this. Reach for
     * it only when `handle()` calls an external service mid-flight — holding a
     * transaction open across a network round-trip is itself the bug — or when a
     * caught constraint violation must not abort a surrounding transaction. The
     * opt-out lives on the call, not the class, so it sits with the caller that
     * set up the external work.
     */
    public function withoutTransaction(): static
    {
        $this->withinTransaction = false;

        return $this;
    }

    /**
     * Invoked when handle() throws, after the transaction has rolled back,
     * before the exception rethrows. Override to undo non-transactional side
     * effects handle() may have already caused — an external charge, a
     * third-party API call. State needed here (e.g. a charge ID) should be
     * captured as an instance property during handle(), the same way $this
     * reaches across both methods for anything else.
     *
     * Takes no params: this method has a body to override, so PHP's ban on
     * narrowing a parameter type would force every override back to the base
     * `Data` type — the exact widening `handle()` exists to avoid. Anything a
     * rollback needs, including the data itself, is captured as an instance
     * property during `handle()`.
     */
    protected function rollback(): void
    {
        //
    }

    /**
     * Assert, then run inside a transaction — in that order, with no way for
     * a subclass to reorder them. `final` because that immovability is the
     * guarantee this class exists to provide. Nesting is supported: an inner
     * action's transaction becomes a savepoint inside the caller's.
     *
     * @param  TData  $data
     * @return TReturn
     *
     * @throws Throwable Whatever a refusing precondition throws.
     */
    final public function execute(Data $data): mixed
    {
        $this->assert($data);

        try {
            /** @var TReturn $returned */
            $returned = $this->withinTransaction
                ? DB::transaction(fn (): mixed => $this->handle($data))
                : $this->handle($data);
        } catch (Throwable $e) {
            try {
                $this->rollback();
            } catch (Throwable $rollbackException) {
                report($rollbackException);
            }

            throw $e;
        }

        return $returned;
    }

    /**
     * Resolve from the container and run in one call. Sugar for
     * `static::make()->execute($data)` at a call site that has no reason to
     * hold the instance. Runs inside a transaction — a call that needs to opt
     * out reaches for `make()->withoutTransaction()->execute()` instead.
     *
     * @param  TData  $data
     * @return TReturn
     *
     * @throws Throwable Whatever a refusing precondition throws.
     */
    public static function run(Data $data): mixed
    {
        return static::make()->execute($data);
    }
}
