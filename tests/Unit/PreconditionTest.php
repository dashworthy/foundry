<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Unit;

use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Preconditions\Precondition;
use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;
use Dashworthy\Foundry\Tests\Fixtures\StubPrecondition;
use RuntimeException;

it('calls $next with the data when it does not refuse', function () {
    $precondition = new StubPrecondition;
    $data = new FixtureActionData;

    $result = $precondition->handle($data, fn (Data $d) => $d);

    expect($result)->toBe($data);
    expect($precondition->evaluations)->toBe(1);
});

it('throws the exception it was configured with instead of calling $next', function () {
    $refusal = new RuntimeException('Refused by fixture.');
    $precondition = new StubPrecondition($refusal);

    $next = fn () => throw new RuntimeException('$next should not have been called.');

    expect(fn () => $precondition->handle(new FixtureActionData, $next))
        ->toThrow(RuntimeException::class, 'Refused by fixture.');
    expect($precondition->evaluations)->toBe(1);
});

it('is a Precondition, and marks its data as Data', function () {
    expect(new StubPrecondition)->toBeInstanceOf(Precondition::class);
    expect(new FixtureActionData)->toBeInstanceOf(Data::class);
});
