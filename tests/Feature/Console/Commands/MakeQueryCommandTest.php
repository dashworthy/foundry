<?php

use App\Domains\Ops\Directory\Queries\ListActiveUsers;
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Support\Facades\File;

/*
 | Every generator is domain-aware: given --domain/--subdomain, each places its
 | class (and any nested Data) under App\Domains\{Domain}\{Sub}\{folder}.
 */

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
