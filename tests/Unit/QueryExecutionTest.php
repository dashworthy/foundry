<?php

declare(strict_types=1);

use Dashworthy\Foundry\Queries\Query;
use Dashworthy\Foundry\Tests\Fixtures\FixtureQuery;
use Dashworthy\Foundry\Tests\Fixtures\FixtureQueryData;
use Dashworthy\Foundry\Tests\Fixtures\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

mutates(Query::class);

beforeEach(function () {
    Schema::create('widgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

it('returns the read rows as an Eloquent collection of models', function () {
    Widget::create(['name' => 'ok']);
    Widget::create(['name' => 'other']);

    $result = (new FixtureQuery)->get(new FixtureQueryData('ok'));

    expect($result)->toBeInstanceOf(Collection::class)
        ->toHaveCount(1)
        ->and($result->first())->toBeInstanceOf(Widget::class)
        ->and($result->first()->name)->toBe('ok');
});

it('builds in handle and lets the base class run the read', function () {
    Widget::create(['name' => 'ok']);

    $query = new FixtureQuery;

    $result = $query->get(new FixtureQueryData('ok'));

    // handle() only returned the builder; the base ran it exactly once.
    expect($query->handleCalls)->toBe(1)
        ->and($result)->toHaveCount(1);
});

it('runs handle again on every call', function () {
    $query = new FixtureQuery;

    $query->get(new FixtureQueryData('ok'));
    $query->get(new FixtureQueryData('ok'));

    expect($query->handleCalls)->toBe(2);
});

it('runs preconditions before handle and stops when one refuses', function () {
    $query = new FixtureQuery;

    expect(fn () => $query->get(new FixtureQueryData('refuse')))
        ->toThrow(RuntimeException::class);

    expect($query->handleCalls)->toBe(0);
});

it('run() resolves from the container and reads in one call', function () {
    Widget::create(['name' => 'ok']);

    $result = FixtureQuery::run(new FixtureQueryData('ok'));

    expect($result)->toHaveCount(1)
        ->and($result->first()->name)->toBe('ok');
});

it('permits returns true when preconditions pass', function () {
    expect((new FixtureQuery)->permits(new FixtureQueryData('ok')))->toBeTrue();
});

it('permits returns false when a precondition throws', function () {
    expect((new FixtureQuery)->permits(new FixtureQueryData('refuse')))->toBeFalse();
});

it('make resolves an instance from the container', function () {
    expect(FixtureQuery::make())->toBeInstanceOf(FixtureQuery::class);
});

it('make throws when the container returns a foreign type', function () {
    // A rogue binding makes the container hand back something that is not the
    // query. make()'s guard must reject it rather than pass it on.
    $this->app->instance(FixtureQuery::class, new stdClass);

    expect(fn (): FixtureQuery => FixtureQuery::make())
        ->toThrow(RuntimeException::class, 'which is not an instance of it.');
});

it('fake replaces the query in the container and can stub handle', function () {
    Widget::create(['name' => 'stubbed']);

    FixtureQuery::fake(fn (MockInterface $mock) => $mock
        ->shouldReceive('handle')->once()->andReturn(Widget::query()->where('name', 'stubbed')));

    // Data says 'ok', but the stubbed handle ignores it and reads 'stubbed'.
    $result = FixtureQuery::make()->get(new FixtureQueryData('ok'));

    expect($result)->toHaveCount(1)
        ->and($result->first()->name)->toBe('stubbed');
});

it('fake stubs preconditions to none by default, bypassing a real refusal', function () {
    Widget::create(['name' => 'refuse']);

    FixtureQuery::fake();

    // 'refuse' is rejected by the real RejectValue precondition; fake() stubs
    // preconditions to none by default, so get() still reaches the real handle().
    $result = FixtureQuery::make()->get(new FixtureQueryData('refuse'));

    expect($result)->toHaveCount(1)
        ->and($result->first()->name)->toBe('refuse');
});

it('fake can stub permits for UI-gate tests', function () {
    FixtureQuery::fake(fn (MockInterface $mock) => $mock
        ->shouldReceive('permits')->andReturn(false));

    expect(FixtureQuery::make()->permits(new FixtureQueryData('ok')))->toBeFalse();
});
