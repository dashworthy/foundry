<?php

use Illuminate\Support\Facades\File;

it('places the class in the stock location when the user picks None', function () {
    $this->artisan('make:action', ['name' => 'Ping'])
        ->expectsQuestion('Which domain should this class belong to?', '__none__')
        ->assertExitCode(0);

    expect(File::exists($this->generatedPath('Actions/Ping.php')))->toBeTrue();
    expect(File::get($this->generatedPath('Actions/Ping.php')))->toContain('namespace App\Actions;');
    expect(File::isDirectory($this->generatedPath('Domains')))->toBeFalse();
});

it('creates a brand new domain and subdomain from typed input', function () {
    $this->artisan('make:action', ['name' => 'Charge'])
        ->expectsQuestion('Which domain should this class belong to?', '__new__')
        ->expectsQuestion('New domain name', 'Billing')
        ->expectsQuestion('Which subdomain within Billing?', '__new__')
        ->expectsQuestion('New subdomain name', 'Invoice')
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Billing/Invoice/Actions/Charge.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Billing\Invoice\Actions;');
});

it('places the class into pre-existing domain and subdomain chosen from the list', function () {
    File::ensureDirectoryExists($this->generatedPath('Domains/Shipping/Freight'));

    $this->artisan('make:action', ['name' => 'Dispatch'])
        ->expectsQuestion('Which domain should this class belong to?', 'Shipping')
        ->expectsQuestion('Which subdomain within Shipping?', 'Freight')
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Shipping/Freight/Actions/Dispatch.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Shipping\Freight\Actions;');
});

it('prompts only for the subdomain when the domain flag is supplied', function () {
    $this->artisan('make:action', ['name' => 'Refund', '--domain' => 'Billing'])
        ->expectsQuestion('Which subdomain within Billing?', '__new__')
        ->expectsQuestion('New subdomain name', 'Orders')
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Billing/Orders/Actions/Refund.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Billing\Orders\Actions;');
});
