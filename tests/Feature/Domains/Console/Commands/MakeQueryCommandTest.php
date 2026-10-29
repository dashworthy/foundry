<?php

use Dashworthy\Foundry\Console\Commands\MakeQueryCommand as CoreMakeQueryCommand;
use Dashworthy\Foundry\Domains\Console\Commands\MakeQueryCommand;
use Dashworthy\Foundry\Domains\DomainNamespace;
use Illuminate\Support\Facades\File;

it('overrides the core make:query with the domain-aware subclass', function () {
    expect($this->app->make(CoreMakeQueryCommand::class))->toBeInstanceOf(MakeQueryCommand::class);
    expect($this->app->make(CoreMakeQueryCommand::class)->getDefinition()->hasOption('domain'))->toBeTrue();
});

it('fails cleanly when run non-interactively without flags', function () {
    $this->artisan('make:query', ['name' => 'ListUsers', '--no-interaction' => true])
        ->expectsOutputToContain('The --domain and --subdomain options are required when running non-interactively.')
        ->assertExitCode(1);

    expect(File::exists($this->generatedPath('Queries/ListUsers.php')))->toBeFalse();
});

it('follows a remapped data folder name in the generated use statement', function () {
    config()->set('foundry.domains.subdirectories.data', 'DTOs');
    $this->app->forgetInstance(DomainNamespace::class);

    $this->artisan('make:query', ['name' => 'ListUsers', '--domain' => 'Auth', '--subdomain' => 'User'])
        ->assertExitCode(0);

    $query = $this->generatedPath('Domains/Auth/User/Queries/ListUsers.php');

    expect(File::get($query))->toContain('use App\Domains\Auth\User\DTOs\ListUsersData;');
    expect(File::exists($this->generatedPath('Domains/Auth/User/DTOs/ListUsersData.php')))->toBeTrue();
});
