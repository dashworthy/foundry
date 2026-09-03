<?php

use App\Domains\Ops\Directory\Queries\ListActiveUsers;
use App\Domains\Ops\Inventory\Actions\AssignInventory;
use App\Domains\Ops\Inventory\Data\AssignInventoryData;
use App\Domains\Ops\Inventory\Preconditions\SufficientInventory;
use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Queries\Query;
use Dashworthy\Foundry\Tests\Fixtures\FixtureActionData;
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

it('creates a query and its data together in a domain', function () {
    $this->artisan('make:query', ['name' => 'ListActiveUsers', '--domain' => 'Ops', '--subdomain' => 'Directory'])
        ->assertExitCode(0);

    $query = $this->generatedPath('Domains/Ops/Directory/Queries/ListActiveUsers.php');
    $data = $this->generatedPath('Domains/Ops/Directory/Data/ListActiveUsersData.php');

    expect(File::exists($query))->toBeTrue();
    expect(File::exists($data))->toBeTrue();

    $contents = File::get($query);
    expect($contents)->toContain('namespace App\Domains\Ops\Directory\Queries;');
    expect($contents)->toContain('use Dashworthy\Foundry\Queries\Query;');
    expect($contents)->toContain('use Illuminate\Database\Eloquent\Builder;');
    expect($contents)->toContain('use App\Domains\Ops\Directory\Data\ListActiveUsersData;');
    expect($contents)->toContain('@extends Query<ListActiveUsersData, Model>');
    expect($contents)->toContain('class ListActiveUsers extends Query');
    expect($contents)->toContain('protected function handle(ListActiveUsersData $data): Builder');
    expect($contents)->toContain('list<\Dashworthy\Foundry\Preconditions\Precondition');

    expect(File::get($data))->toContain('final readonly class ListActiveUsersData extends Data');
});

it('generates a query whose handle() the base class can reach', function () {
    $this->artisan('make:query', ['name' => 'ListActiveUsers', '--domain' => 'Ops', '--subdomain' => 'Directory'])
        ->assertExitCode(0);

    require $this->generatedPath('Domains/Ops/Directory/Queries/ListActiveUsers.php');
    require $this->generatedPath('Domains/Ops/Directory/Data/ListActiveUsersData.php');

    $query = new ListActiveUsers;

    expect($query)->toBeInstanceOf(Query::class);

    // `Query` declares handle() as a `@method`, not a real abstract method, so a
    // stub that forgot handle() would not fatal at class-declaration time — it
    // would fail on first get(). Asserting the generated class actually declares
    // handle() is what proves the stub emitted the method the base class calls.
    expect((new ReflectionClass($query))->hasMethod('handle'))->toBeTrue();
});

it('creates a data object on its own in a domain', function () {
    $this->artisan('make:data', ['name' => 'ArchiveReportData', '--domain' => 'Ops', '--subdomain' => 'Reports'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Ops/Reports/Data/ArchiveReportData.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('final readonly class ArchiveReportData extends Data');
});

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
