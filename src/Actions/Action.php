<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Actions;

use Closure;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Preconditions\Precondition;
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Pipeline;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Throwable;

/**
 * A write expressed as one class: it takes exactly one {@see Data} object, runs
 * its preconditions, does the work inside a database transaction, and returns
 * whatever the work produced. Its read-side twin is {@see Query}.
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
    /**
     * Whether execute() wraps handle() in a database transaction. `null` means
     * "not set", which execute() resolves to the transactional baseline; a call
     * site drops the transaction by setting it `false` with withoutTransaction().
     *
     * The baseline lives in execute() as an executed `?? true`, not as a property
     * default, so a mutation flipping it is exercised by a test — a `true`
     * default on the declaration line is never marked covered.
     */
    private ?bool $withinTransaction = null;

    /**
     * Resolve this action from the container.
     *
     * Sugar over `app(static::class)` for call sites that cannot inject —
     * a controller method already full of dependencies, a console command, a
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
        $action = App::make(static::class);

        if (! $action instanceof static) {
            throw new RuntimeException(sprintf(
                'The container returned %s for %s, which is not an instance of it.',
                get_debug_type($action),
                static::class,
            ));
        }

        return $action;
    }

    /**
     * Replace this action in the container with a partial mock.
     *
     * `preconditions()` is stubbed to none by default, so a test faking a
     * collaborator does not have to satisfy that action's authorization rules.
     * The stub is registered `byDefault()`, so anything the closure declares
     * replaces it.
     *
     * `handle()` is deliberately NOT stubbed by default, though the closure can
     * stub it — protected-method mocking is enabled for exactly that. There is
     * no type-safe default: Mockery honours the declared return type, so
     * stubbing `handle()` to null is a `TypeError` on every action that returns
     * something non-nullable, and a full mock throws `BadMethodCallException`
     * on any call it has no expectation for. A test that needs the work
     * replaced supplies a value of the right type:
     *
     *     PublishPost::fake(fn (MockInterface $mock) => $mock
     *         ->shouldReceive('handle')->once()->andReturn($post));
     *
     * The seam is `handle()` and `preconditions()`, never `execute()`.
     * `execute()` is `final`, so Mockery cannot override it: a
     * `shouldReceive('execute')` is accepted and then silently ignored while
     * the real method runs.
     *
     * Mockery is a test-time dependency and deliberately not a runtime
     * `require` of this package, so calling this without it fails with a
     * sentence rather than an undefined-class error.
     *
     * @param  (Closure(MockInterface): mixed)|null  $closure
     */
    public static function fake(?Closure $closure = null): MockInterface
    {
        if (! class_exists(Mockery::class)) {
            throw new RuntimeException(sprintf(
                '%s::fake() needs mockery/mockery, which is a dev dependency. Install it to fake actions in tests.',
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
     * stops at the first that throws, so an authorization check placed ahead
     * of a state check means an unauthorized actor never learns the state.
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
     * Whether this action would be permitted — for deciding if a control
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

        $withinTransaction = $this->withinTransaction ?? true;

        try {
            /** @var TReturn $returned */
            $returned = $withinTransaction
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
