<?php

declare(strict_types=1);

use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;
use Dashworthy\Foundry\Tests\Fixtures\RefusingAction;
use Dashworthy\Foundry\Tests\Fixtures\StubPrecondition;
use Mockery\MockInterface;

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
