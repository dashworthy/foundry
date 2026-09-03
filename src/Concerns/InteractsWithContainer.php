<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Concerns;

use Closure;
use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Support\Facades\App;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

/**
 * The container lifecycle shared by {@see Action}
 * and {@see Query}: resolve the unit from the
 * container, or replace it with a partial mock in a test. Both sides want the
 * exact same behaviour here, so it lives in one place.
 */
trait InteractsWithContainer
{
    /**
     * Resolve this unit from the container.
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
        $instance = App::make(static::class);

        if (! $instance instanceof static) {
            throw new RuntimeException(sprintf(
                'The container returned %s for %s, which is not an instance of it.',
                get_debug_type($instance),
                static::class,
            ));
        }

        return $instance;
    }

    /**
     * Replace this unit in the container with a partial mock.
     *
     * `preconditions()` is stubbed to none by default, so a test faking a
     * collaborator does not have to satisfy that unit's authorization rules.
     * The stub is registered `byDefault()`, so anything the closure declares
     * replaces it.
     *
     * `handle()` is deliberately NOT stubbed by default, though the closure can
     * stub it — protected-method mocking is enabled for exactly that. There is
     * no type-safe default: Mockery honours the declared return type, so
     * stubbing `handle()` to null is a `TypeError` on every unit that returns
     * something non-nullable, and a full mock throws `BadMethodCallException`
     * on any call it has no expectation for. A test that needs the work
     * replaced supplies a value of the right type:
     *
     *     PublishPost::fake(fn (MockInterface $mock) => $mock
     *         ->shouldReceive('handle')->once()->andReturn($post));
     *
     * The seam is `handle()` and `preconditions()`, never `execute()`/`get()`.
     * Those are `final`, so Mockery cannot override them: a
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
                '%s::fake() needs mockery/mockery, which is a dev dependency. Install it to fake this class in tests.',
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
}
