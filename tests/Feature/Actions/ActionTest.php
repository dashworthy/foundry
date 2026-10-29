<?php

declare(strict_types=1);

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Tests\Fixtures\FixtureAction;
use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;
use Dashworthy\Foundry\Tests\Fixtures\NestingAction;
use Dashworthy\Foundry\Tests\Fixtures\RefusingAction;
use Dashworthy\Foundry\Tests\Fixtures\StubPrecondition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

mutates(Action::class);

beforeEach(function () {
    Schema::create('widgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

it('returns what handle returns', function () {
    expect((new FixtureAction)->execute(new FixtureActionData('result')))->toBe('result');
});

it('run() resolves from the container and executes in one call', function () {
    expect(FixtureAction::run(new FixtureActionData('via-run')))->toBe('via-run');
});

it('refuses before opening a transaction and never runs handle', function () {
    $action = new FixtureAction([new StubPrecondition(new RuntimeException('Refused by fixture.'))]);

    expect(fn () => $action->execute(new FixtureActionData))->toThrow(RuntimeException::class, 'Refused by fixture.');
    expect($action->handled)->toBe(0);
    expect(DB::transactionLevel())->toBe(0);
});

it('stops the precondition chain at the first that throws, never running later pipes', function () {
    $first = new StubPrecondition;
    $second = new StubPrecondition(new RuntimeException('Refused by fixture.'));
    $third = new StubPrecondition;

    $action = new FixtureAction([$first, $second, $third]);

    expect(fn () => $action->execute(new FixtureActionData))->toThrow(RuntimeException::class, 'Refused by fixture.');
    expect($first->evaluations)->toBe(1);
    expect($second->evaluations)->toBe(1);
    expect($third->evaluations)->toBe(0);
    expect($action->handled)->toBe(0);
});

it('rolls back the work when handle throws', function () {
    $action = new FixtureAction(work: function (): string {
        DB::table('widgets')->insert(['name' => 'doomed']);

        throw new RuntimeException('boom');
    });

    expect(fn () => $action->execute(new FixtureActionData))->toThrow(RuntimeException::class, 'boom');
    expect(DB::table('widgets')->count())->toBe(0);
});

it('runs normally when called from inside a caller-managed transaction', function () {
    $action = new FixtureAction(work: function (): string {
        DB::table('widgets')->insert(['name' => 'inside caller transaction']);

        return 'done';
    });

    DB::transaction(function () use ($action): void {
        expect($action->execute(new FixtureActionData))->toBe('done');
        expect($action->handled)->toBe(1);
    });

    expect(DB::table('widgets')->count())->toBe(1);
});

it('discards its own committed work when the caller-managed transaction rolls back', function () {
    $action = new FixtureAction(work: function (): string {
        DB::table('widgets')->insert(['name' => 'doomed by caller']);

        return 'done';
    });

    try {
        DB::transaction(function () use ($action): void {
            $action->execute(new FixtureActionData);

            throw new RuntimeException('caller rolled back');
        });
    } catch (RuntimeException) {
        // Expected — the caller's transaction is what we're asserting on.
    }

    expect(DB::table('widgets')->count())->toBe(0);
});

it('rolls back a nested action\'s writes when the outer action fails', function () {
    $inner = new FixtureAction(work: function (): string {
        DB::table('widgets')->insert(['name' => 'inner']);

        return 'inner';
    });

    $outer = new NestingAction($inner, function (): string {
        throw new RuntimeException('boom');
    });

    expect(fn () => $outer->execute(new FixtureActionData))->toThrow(RuntimeException::class, 'boom');
    expect($inner->handled)->toBe(1);
    expect(DB::table('widgets')->count())->toBe(0);
});

it('opens no transaction when the call opts out with withoutTransaction()', function () {
    $action = new FixtureAction(work: function (): string {
        expect(DB::transactionLevel())->toBe(0);

        return 'done';
    });

    expect($action->withoutTransaction()->execute(new FixtureActionData))->toBe('done');
});

it('wraps handle in a transaction by default', function () {
    $action = new FixtureAction(work: function (): string {
        expect(DB::transactionLevel())->toBe(1);

        return 'done';
    });

    expect($action->execute(new FixtureActionData))->toBe('done');
});

it('withoutTransaction() returns the same action for chaining', function () {
    $action = new FixtureAction;

    expect($action->withoutTransaction())->toBe($action);
});

it('calls rollback when handle throws', function () {
    $action = new FixtureAction(work: function (): string {
        throw new RuntimeException('boom');
    });

    expect(fn () => $action->execute(new FixtureActionData))->toThrow(RuntimeException::class, 'boom');
    expect($action->rolledBack)->toBe(1);
});

it('does not call rollback when a precondition refuses', function () {
    $action = new FixtureAction([new StubPrecondition(new RuntimeException('Refused by fixture.'))]);

    expect(fn () => $action->execute(new FixtureActionData))->toThrow(RuntimeException::class);
    expect($action->rolledBack)->toBe(0);
});

it('does not call rollback when handle succeeds', function () {
    $action = new FixtureAction;

    $action->execute(new FixtureActionData);

    expect($action->rolledBack)->toBe(0);
});

it('reports a throwing rollback and still rethrows the original exception', function () {
    Exceptions::fake();

    $action = new FixtureAction(
        work: function (): string {
            throw new RuntimeException('original failure');
        },
        onRollback: function (): void {
            throw new RuntimeException('rollback failure');
        },
    );

    expect(fn () => $action->execute(new FixtureActionData))
        ->toThrow(RuntimeException::class, 'original failure');

    Exceptions::assertReported(
        fn (RuntimeException $e): bool => $e->getMessage() === 'rollback failure',
    );
});

it('calls rollback whether or not the action is transactional', function () {
    $action = new FixtureAction(
        work: function (): string {
            throw new RuntimeException('boom');
        },
    );

    expect(fn () => $action->withoutTransaction()->execute(new FixtureActionData))->toThrow(RuntimeException::class);
    expect($action->rolledBack)->toBe(1);
});

it('runs handle past a refusing precondition when the call opts out with withoutPreconditions()', function () {
    $action = new FixtureAction([new StubPrecondition(new RuntimeException('Refused by fixture.'))]);

    expect($action->withoutPreconditions()->execute(new FixtureActionData('done')))->toBe('done');
    expect($action->handled)->toBe(1);
});

it('withoutPreconditions() returns the same action for chaining', function () {
    $action = new FixtureAction;

    expect($action->withoutPreconditions())->toBe($action);
});

/*
 | `make()` is sugar over the container, and the reason it exists rather than
 | `new` is that `new` bypasses any binding. These tests pin that difference:
 | the second one fails if make() is ever "simplified" to `new static`.
 */

it('resolves an action from the container', function (): void {
    expect(FixtureAction::make())->toBeInstanceOf(FixtureAction::class);
});

it('returns the bound instance rather than a fresh one', function (): void {
    $bound = new FixtureAction;
    $this->app->instance(FixtureAction::class, $bound);

    expect(FixtureAction::make())->toBe($bound);
});

it('resolves the called class, not the base class', function (): void {
    expect(FixtureAction::make())->toBeInstanceOf(FixtureAction::class);

    $resolved = FixtureAction::make();

    expect($resolved->execute(new FixtureActionData('resolved')))->toBe('resolved');
});

/*
 | fake() stubs preconditions() and lets the closure stub handle(), never
 | execute(). execute() is final, so Mockery cannot override it: a
 | shouldReceive('execute') is accepted and then silently ignored while the real
 | method runs. The two protected methods it calls are overridable, and stubbing
 | them gets the same effect without that trap.
 |
 | RefusingAction declares both on the class rather than taking them through the
 | constructor. That matters: Mockery builds a partial without calling the
 | constructor, so a fixture that injected its preconditions would have none in
 | the mock regardless, and these tests would pass with the stubbing removed.
 */

it('binds a partial mock into the container', function (): void {
    $fake = RefusingAction::fake();

    expect($fake)->toBeInstanceOf(MockInterface::class)
        ->and(RefusingAction::make())->toBe($fake);
});

/*
 | The control: unfaked, this action refuses. Every test below depends on that,
 | so it is asserted rather than assumed.
 */
it('refuses when it is not faked', function (): void {
    expect(fn () => RefusingAction::make()->execute(new FixtureActionData))
        ->toThrow(RuntimeException::class, 'refused');
});

it('refuses nothing by default', function (): void {
    RefusingAction::fake();

    expect(RefusingAction::make()->execute(new FixtureActionData))->toBe('worked');
});

/*
 | handle() is not stubbed by default — there is no type-safe value to stub it
 | with — but the closure can, because fake() enables protected-method mocking.
 | This is the test that fails if shouldAllowMockingProtectedMethods() is
 | dropped: Mockery refuses to set an expectation on a protected method.
 */
it('lets the closure stub the work', function (): void {
    RefusingAction::fake(function (MockInterface $mock): void {
        $mock->shouldReceive('handle')->once()->andReturn('stubbed');
    });

    expect(RefusingAction::make()->execute(new FixtureActionData))->toBe('stubbed');
});

/*
 | byDefault() is what lets the closure win over the inert stub. Without it
 | Mockery keeps the first matching expectation and the closure is silently
 | ignored — this is what catches that.
 */
it('lets the closure put preconditions back', function (): void {
    $refusing = new StubPrecondition(new RuntimeException('closure refusal'));

    RefusingAction::fake(function (MockInterface $mock) use ($refusing): void {
        $mock->shouldReceive('preconditions')->andReturn([$refusing]);
    });

    expect(fn () => RefusingAction::make()->execute(new FixtureActionData))
        ->toThrow(RuntimeException::class, 'closure refusal');

    expect($refusing->evaluations)->toBe(1);
});
