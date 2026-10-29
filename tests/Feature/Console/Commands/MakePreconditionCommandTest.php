<?php

use App\Domains\Ops\Inventory\Preconditions\SufficientInventory;
use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;
use Illuminate\Support\Facades\File;

/*
 | Every generator is domain-aware: given --domain/--subdomain, each places its
 | class (and any nested Data) under App\Domains\{Domain}\{Sub}\{folder}.
 */

it('creates a precondition pipe that passes through to $next by default', function () {
    $this->artisan('make:precondition', ['name' => 'SufficientInventory', '--domain' => 'Ops', '--subdomain' => 'Inventory'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Ops/Inventory/Preconditions/SufficientInventory.php');
    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Domains\Ops\Inventory\Preconditions;');
    expect($contents)->toContain('implements Precondition');
    expect($contents)->toContain('public function handle(Data $data, Closure $next): mixed');

    require $path;

    $precondition = new SufficientInventory;
    $data = new FixtureActionData;

    expect($precondition->handle($data, fn (mixed $passed) => $passed))->toBe($data);
});
