<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Queries;

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Concerns\EvaluatesPreconditions;
use Dashworthy\Foundry\Concerns\InteractsWithContainer;
use Dashworthy\Foundry\Data\Data;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A read expressed as one class: it takes exactly one {@see Data} object,
 * evaluates its preconditions, runs the read, and returns the rows as a
 * `Collection` of models. It mirrors the write side, {@see Action}
 * — same `make()`/`run()`/`fake()` helpers, same precondition pipeline — minus
 * the database transaction, because a read changes nothing to roll back.
 *
 * The container lifecycle (`make()`, `fake()`) and the precondition pipeline
 * (`preconditions()`, `assert()`, `permits()`, `withoutPreconditions()`) are
 * identical on both sides and live in {@see InteractsWithContainer} and
 * {@see EvaluatesPreconditions}. What is unique to the read side stays here:
 * building the read and materialising it.
 *
 * The split of labour is deliberate: `handle()` only *builds* the read and
 * hands back the Eloquent builder; the base runs it. A concrete query never
 * calls `->get()`. That keeps "how a read is executed" in one place — the base
 * asserts, builds, then materialises the builder into a `Collection` — so a
 * subclass cannot forget to run it, run it twice, or run it before the
 * preconditions have passed.
 *
 * A query does not shape its results either. It returns the models it read;
 * turning those into whatever a page, an export, or an API needs is the
 * caller's job. The `TModel` template types the element the `Collection` holds
 * so callers keep that type without a cast.
 *
 * `handle()` is declared here as `@method` rather than as a real abstract
 * method so a concrete query can typehint its own `{Name}Data` —
 * `handle(PendingInvitationsData $data)`, not `handle(Data $data)`. PHP forbids
 * narrowing a parameter type in an override, so an abstract `handle(Data $data)`
 * here would fatal every subclass that named its real data class. The
 * annotation keeps the narrow type in the signature, where a wrong argument is
 * a `TypeError` at the boundary instead of an undefined-property error midway
 * through the read.
 *
 * The trade is that a subclass missing `handle()` no longer fatals at
 * class-declaration time; it fails on first `get()`. Applications should buy
 * that back with an architecture test asserting every `Query` subclass declares
 * `handle()`.
 *
 * @template TData of Data
 * @template TModel of Model
 *
 * @method Builder<TModel> handle(TData $data) Build the read query. No writes; the base runs it.
 */
abstract class Query
{
    /** @use EvaluatesPreconditions<TData> */
    use EvaluatesPreconditions;

    use InteractsWithContainer;

    /**
     * Assert, build, then run — in that order, with no way for a subclass to
     * reorder them. `final` because that immovability is the guarantee this
     * class exists to provide. Authorization runs before the read, so a refused
     * actor never reaches `handle()`, and the base — not the subclass — is what
     * turns the builder into rows.
     *
     * @param  TData  $data
     * @return Collection<int, TModel>
     *
     * @throws Throwable Whatever a refusing precondition throws.
     */
    final public function get(Data $data): Collection
    {
        $this->assert($data);

        /** @var Collection<int, TModel> $result */
        $result = $this->handle($data)->get();

        return $result;
    }

    /**
     * Resolve from the container and read in one call — the read-side twin of
     * `Action::run()`. Sugar for `static::make()->get($data)` at a call site
     * that has no reason to hold the instance.
     *
     * @param  TData  $data
     * @return Collection<int, TModel>
     *
     * @throws Throwable Whatever a refusing precondition throws.
     */
    public static function run(Data $data): Collection
    {
        return static::make()->get($data);
    }
}
