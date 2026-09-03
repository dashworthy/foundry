<?php

use Dashworthy\Foundry\Tests\Fixtures\DomainAwareFixtureCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->app[Kernel::class]->registerCommand($this->app->make(DomainAwareFixtureCommand::class));
});

it('a custom command using the trait is domain-aware', function () {
    $this->artisan('make:fixture-thing', ['name' => 'Widget', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Billing/Invoice/FixtureThings/Widget.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Billing\Invoice\FixtureThings;');
});

it('a custom command using the trait exposes the domain options', function () {
    $definition = $this->app->make(DomainAwareFixtureCommand::class)->getDefinition();

    expect($definition->hasOption('domain'))->toBeTrue()
        ->and($definition->hasOption('subdomain'))->toBeTrue();
});
