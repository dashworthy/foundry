<?php

declare(strict_types=1);

use Dashworthy\Foundry\Tests\Fixtures\FixtureAction;
use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;

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
