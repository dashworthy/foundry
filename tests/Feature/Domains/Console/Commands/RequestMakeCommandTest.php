<?php

use Dashworthy\Foundry\Domains\DomainNamespace;
use Illuminate\Support\Facades\File;

it('uses the configured custom folder names', function () {
    $this->artisan('make:request', ['name' => 'StoreInvoice', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);
    $this->artisan('make:resource', ['name' => 'InvoiceResource', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);

    expect(File::exists($this->generatedPath('Domains/Billing/Invoice/FormRequests/StoreInvoice.php')))->toBeTrue();
    expect(File::exists($this->generatedPath('Domains/Billing/Invoice/JsonResources/InvoiceResource.php')))->toBeTrue();
});

it('honors a runtime config override of a folder name', function () {
    config()->set('foundry.domains.subdirectories.request', 'Requests');
    $this->app->forgetInstance(DomainNamespace::class);

    $this->artisan('make:request', ['name' => 'OverrideReq', '--domain' => 'Billing', '--subdomain' => 'Invoice'])
        ->assertExitCode(0);

    expect(File::exists($this->generatedPath('Domains/Billing/Invoice/Requests/OverrideReq.php')))->toBeTrue();
});
