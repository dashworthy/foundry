<?php

use Dashworthy\Foundry\Console\Commands\MakePreconditionCommand as CoreMakePreconditionCommand;
use Dashworthy\Foundry\Domains\Console\Commands\MakePreconditionCommand;
use Illuminate\Support\Facades\File;

it('overrides the core make:precondition with the domain-aware subclass', function () {
    expect($this->app->make(CoreMakePreconditionCommand::class))->toBeInstanceOf(MakePreconditionCommand::class);
    expect($this->app->make(CoreMakePreconditionCommand::class)->getDefinition()->hasOption('domain'))->toBeTrue();
});

it('fails cleanly when run non-interactively without flags', function () {
    $this->artisan('make:precondition', ['name' => 'RequiresHigherRank', '--no-interaction' => true])
        ->expectsOutputToContain('The --domain and --subdomain options are required when running non-interactively.')
        ->assertExitCode(1);
});

it('creates a precondition inside a domain', function () {
    $this->artisan('make:precondition', ['name' => 'RequiresHigherRank', '--domain' => 'Auth', '--subdomain' => 'User'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Auth/User/Preconditions/RequiresHigherRank.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Auth\User\Preconditions;');
});
