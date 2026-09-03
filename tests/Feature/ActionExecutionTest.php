<?php

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Tests\Fixtures\FixtureAction;
use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;
use Dashworthy\Foundry\Tests\Fixtures\NestingAction;
use Dashworthy\Foundry\Tests\Fixtures\StubPrecondition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;

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
