<?php

use Dashworthy\Foundry\Console\Commands\MakeDataCommand as CoreMakeDataCommand;
use Dashworthy\Foundry\Domains\Console\Commands\MakeDataCommand;
use Illuminate\Support\Facades\File;

it('overrides the core make:data with the domain-aware subclass', function () {
    expect($this->app->make(CoreMakeDataCommand::class))->toBeInstanceOf(MakeDataCommand::class);
    expect($this->app->make(CoreMakeDataCommand::class)->getDefinition()->hasOption('domain'))->toBeTrue();
});

it('fails cleanly when run non-interactively without flags', function () {
    $this->artisan('make:data', ['name' => 'ArchiveUserData', '--no-interaction' => true])
        ->expectsOutputToContain('The --domain and --subdomain options are required when running non-interactively.')
        ->assertExitCode(1);
});

it('creates a data object inside a domain', function () {
    $this->artisan('make:data', ['name' => 'ArchiveUserData', '--domain' => 'Auth', '--subdomain' => 'User'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Auth/User/Data/ArchiveUserData.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Auth\User\Data;');
});
