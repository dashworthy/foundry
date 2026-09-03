<?php

use Dashworthy\Foundry\Domains\Console\Commands\ModelMakeCommand;
use Dashworthy\Foundry\Domains\DomainNamespace;
use Illuminate\Foundation\Console\ModelMakeCommand as CoreModelMakeCommand;
use Illuminate\Support\Facades\File;

it('overrides the core make:model with the domain-aware subclass', function () {
    expect($this->app->make(CoreModelMakeCommand::class))->toBeInstanceOf(ModelMakeCommand::class);
});

it('places a model into the domain path with the correct namespace', function () {
    $this->artisan('make:model', ['name' => 'Invoice', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Billing/Invoice/Models/Invoice.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Billing\Invoice\Models;');
});

it('uses the configured custom folder names', function () {
    $this->artisan('make:request', ['name' => 'StoreInvoice', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);
    $this->artisan('make:resource', ['name' => 'InvoiceResource', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);

    expect(File::exists($this->generatedPath('Domains/Billing/Invoice/FormRequests/StoreInvoice.php')))->toBeTrue();
    expect(File::exists($this->generatedPath('Domains/Billing/Invoice/JsonResources/InvoiceResource.php')))->toBeTrue();
});

it('fails cleanly when run non-interactively without a domain flag', function () {
    $this->artisan('make:model', ['name' => 'Plain', '--no-interaction' => true])
        ->expectsOutputToContain('The --domain and --subdomain options are required when running non-interactively.')
        ->assertExitCode(1);

    expect(File::exists($this->generatedPath('Models/Plain.php')))->toBeFalse();
    expect(File::exists($this->generatedPath('Domains/Plain.php')))->toBeFalse();
});

it('fails with a clean error when domain is provided without subdomain non-interactively', function () {
    $this->artisan('make:model', ['name' => 'Broken', '--domain' => 'Billing', '--no-interaction' => true])
        ->expectsOutputToContain('The --subdomain option is required when --domain is provided.')
        ->assertExitCode(1);

    expect(File::exists($this->generatedPath('Domains/Billing/Models/Broken.php')))->toBeFalse();
});

it('honors a runtime config override of a folder name', function () {
    config()->set('foundry.domains.subdirectories.request', 'Requests');
    $this->app->forgetInstance(DomainNamespace::class);

    $this->artisan('make:request', ['name' => 'OverrideReq', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);

    expect(File::exists($this->generatedPath('Domains/Billing/Invoice/Requests/OverrideReq.php')))->toBeTrue();
});
