<?php

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Tests\Fixtures\FixtureAction;
use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;
use Dashworthy\Foundry\Tests\Fixtures\StubPrecondition;

mutates(Action::class);

it('does not throw when there are no preconditions', function () {
    (new FixtureAction)->assert(new FixtureActionData);
})->throwsNoExceptions();

it('evaluates preconditions in declaration order and stops at the first that throws', function () {
    $first = new StubPrecondition;
    $second = new StubPrecondition(new RuntimeException('Refused by fixture.'));
    $third = new StubPrecondition;

    $action = new FixtureAction([$first, $second, $third]);

    expect(fn () => $action->assert(new FixtureActionData))
        ->toThrow(RuntimeException::class, 'Refused by fixture.');
    expect($first->evaluations)->toBe(1);
    expect($second->evaluations)->toBe(1);
    expect($third->evaluations)->toBe(0);
});

it('answers allows() without throwing', function () {
    $refusing = new FixtureAction([new StubPrecondition(new RuntimeException('Refused by fixture.'))]);

    expect($refusing->permits(new FixtureActionData))->toBeFalse();
    expect((new FixtureAction)->permits(new FixtureActionData))->toBeTrue();
});

it('propagates the precondition\'s own exception unchanged, unwrapped', function () {
    $refusal = new RuntimeException('Refused by fixture.');
    $action = new FixtureAction([new StubPrecondition($refusal)]);

    try {
        $action->assert(new FixtureActionData);
        $caught = null;
    } catch (Throwable $e) {
        $caught = $e;
    }

    expect($caught)->toBe($refusal);
});

it('does not throw when every precondition passes', function () {
    (new FixtureAction([new StubPrecondition]))->assert(new FixtureActionData);
})->throwsNoExceptions();

it('make throws when the container returns a foreign type', function () {
    // A rogue binding makes the container hand back something that is not the
    // action. make()'s guard must reject it rather than pass it on.
    $this->app->instance(FixtureAction::class, new stdClass);

    expect(fn (): FixtureAction => FixtureAction::make())
        ->toThrow(RuntimeException::class, 'which is not an instance of it.');
});
