<?php

use App\Domains\Ops\Inventory\Actions\AssignInventory;
use App\Domains\Ops\Inventory\Data\AssignInventoryData;
use Dashworthy\Foundry\Actions\Action;
use Illuminate\Support\Facades\File;

/*
 | Every generator is domain-aware: given --domain/--subdomain, each places its
 | class (and any nested Data) under App\Domains\{Domain}\{Sub}\{folder}.
 */

it('creates an action and its data together in a domain', function () {
    $this->artisan('make:action', ['name' => 'AssignInventory', '--domain' => 'Ops', '--subdomain' => 'Inventory'])
        ->assertExitCode(0);

    $action = $this->generatedPath('Domains/Ops/Inventory/Actions/AssignInventory.php');
    $data = $this->generatedPath('Domains/Ops/Inventory/Data/AssignInventoryData.php');

    expect(File::exists($action))->toBeTrue();
    expect(File::exists($data))->toBeTrue();

    $contents = File::get($action);
    expect($contents)->toContain('namespace App\Domains\Ops\Inventory\Actions;');
    expect($contents)->toContain('use Dashworthy\Foundry\Actions\Action;');
    expect($contents)->toContain('use App\Domains\Ops\Inventory\Data\AssignInventoryData;');
    expect($contents)->toContain('@extends Action<AssignInventoryData, null>');
    expect($contents)->toContain('class AssignInventory extends Action');
    expect($contents)->toContain('protected function handle(AssignInventoryData $data): mixed');
    expect($contents)->toContain('list<\Dashworthy\Foundry\Preconditions\Precondition');

    expect(File::get($data))->toContain('final readonly class AssignInventoryData extends Data');
});

it('generates an action that is loadable and runnable with zero configuration', function () {
    $this->artisan('make:action', ['name' => 'AssignInventory', '--domain' => 'Ops', '--subdomain' => 'Inventory'])
        ->assertExitCode(0);

    require $this->generatedPath('Domains/Ops/Inventory/Actions/AssignInventory.php');
    require $this->generatedPath('Domains/Ops/Inventory/Data/AssignInventoryData.php');

    $action = new AssignInventory;

    expect($action)->toBeInstanceOf(Action::class);

    // `Action` declares handle() as `@method`, not as a real abstract method,
    // so a missing or mis-typed handle() no longer fatals at class-declaration
    // time — it fails on first execute(). Running the generated pair is what
    // proves the stub emitted a handle() the base class can actually reach.
    expect($action->execute(new AssignInventoryData))->toBeNull();
});
