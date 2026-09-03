<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Queries;

use Closure;
use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Preconditions\Precondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Pipeline;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Throwable;

/**
 * A read expressed as one class: it takes exactly one {@see Data} object,
 * evaluates its preconditions, runs the read, and returns the rows as a
 * `Collection` of models. It mirrors the write side, {@see Action}
 * — same `make()`/`run()`/`fake()` helpers, same precondition pipeline — minus
 * the database transaction, because a read changes nothing to roll back.
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
    /**
     * Resolve this query from the container.
     *
     * Sugar over `app(static::class)` for call sites that cannot inject — a
     * controller method already full of dependencies, a console command, a
     * closure. Constructor injection remains the better option wherever it is
     * available; this exists so that reaching for `new` is never the easier
     * path, since `new` skips the container and any binding pointing at a
     * decorated or faked instance.
     *
     * The `instanceof` guard is not defensive noise: the container is a
     * rebindable registry, so `App::make()` returns whatever is bound for this
     * class, which a consumer can point anywhere. Catching that here names the
     * problem at the call that caused it, rather than as a `TypeError` several
     * frames later.
     */
    public static function make(): static
    {
        $query = App::make(static::class);

        if (! $query instanceof static) {
            throw new RuntimeException(sprintf(
                'The container returned %s for %s, which is not an instance of it.',
                get_debug_type($query),
                static::class,
            ));
        }

        return $query;
    }

    /**
     * Replace this query in the container with a partial mock.
     *
     * `preconditions()` is stubbed to none by default, so a test faking a
     * collaborator does not have to satisfy that query's authorization rules.
     * The stub is registered `byDefault()`, so anything the closure declares
     * replaces it.
     *
     * `handle()` is deliberately NOT stubbed by default, though the closure can
     * stub it — protected-method mocking is enabled for exactly that. There is
     * no type-safe default: Mockery honours the declared return type, so
     * stubbing `handle()` to null is a `TypeError` on every query that returns
     * something non-nullable.
     *
     * The seam is `handle()` and `preconditions()`, never `get()`. `get()` is
     * `final`, so Mockery cannot override it: a `shouldReceive('get')` is
     * accepted and then silently ignored while the real method runs.
     *
     * Mockery is a test-time dependency and deliberately not a runtime `require`
     * of this package, so calling this without it fails with a sentence rather
     * than an undefined-class error.
     *
     * @param  (Closure(MockInterface): mixed)|null  $closure
     */
    public static function fake(?Closure $closure = null): MockInterface
    {
        if (! class_exists(Mockery::class)) {
            throw new RuntimeException(sprintf(
                '%s::fake() needs mockery/mockery, which is a dev dependency. Install it to fake queries in tests.',
                static::class,
            ));
        }

        $mock = Mockery::mock(static::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->shouldReceive('preconditions')->andReturn([])->byDefault();

        if ($closure !== null) {
            $closure($mock);
        }

        App::instance(static::class, $mock);

        return $mock;
    }

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
     * Runs every precondition in order, stopping at the first that throws.
     * Whatever exception a precondition throws propagates unchanged — this
     * package neither wraps nor replaces it.
     *
     * @param  TData  $data
     */
    public function assert(Data $data): void
    {
        Pipeline::send($data)->through($this->preconditions())->thenReturn();
    }

    /**
     * Whether this query would be permitted — for deciding if a control
     * renders, so the UI and the server cannot drift.
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
