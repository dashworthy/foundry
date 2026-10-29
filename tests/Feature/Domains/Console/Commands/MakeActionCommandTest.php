<?php

use Dashworthy\Foundry\Console\Commands\MakeActionCommand as CoreMakeActionCommand;
use Dashworthy\Foundry\Domains\Console\Commands\MakeActionCommand;
use Dashworthy\Foundry\Domains\DomainNamespace;
use Illuminate\Support\Facades\File;

it('overrides the core make:action with the domain-aware subclass', function () {
    expect($this->app->make(CoreMakeActionCommand::class))->toBeInstanceOf(MakeActionCommand::class);
    expect($this->app->make(CoreMakeActionCommand::class)->getDefinition()->hasOption('domain'))->toBeTrue();
});

it('fails cleanly when run non-interactively without flags', function () {
    $this->artisan('make:action', ['name' => 'SendInvoice', '--no-interaction' => true])
        ->expectsOutputToContain('The --domain and --subdomain options are required when running non-interactively.')
        ->assertExitCode(1);

    expect(File::exists($this->generatedPath('Actions/SendInvoice.php')))->toBeFalse();
});

it('creates an action and its data inside a domain', function () {
    $this->artisan('make:action', ['name' => 'CreateUser', '--domain' => 'Auth', '--subdomain' => 'User'])
        ->assertExitCode(0);

    $action = $this->generatedPath('Domains/Auth/User/Actions/CreateUser.php');
    $data = $this->generatedPath('Domains/Auth/User/Data/CreateUserData.php');

    expect(File::exists($action))->toBeTrue();
    expect(File::exists($data))->toBeTrue();
    expect(File::get($action))->toContain('namespace App\Domains\Auth\User\Actions;');
    expect(File::get($action))->toContain('use App\Domains\Auth\User\Data\CreateUserData;');
    expect(File::get($action))->toContain('@extends Action<CreateUserData, null>');
    expect(File::get($data))->toContain('namespace App\Domains\Auth\User\Data;');
});

it('follows a remapped data folder name in the generated use statement', function () {
    config()->set('foundry.domains.subdirectories.data', 'DTOs');
    $this->app->forgetInstance(DomainNamespace::class);

    $this->artisan('make:action', ['name' => 'CreateUser', '--domain' => 'Auth', '--subdomain' => 'User'])
        ->assertExitCode(0);

    $action = $this->generatedPath('Domains/Auth/User/Actions/CreateUser.php');
    $data = $this->generatedPath('Domains/Auth/User/DTOs/CreateUserData.php');

    expect(File::exists($action))->toBeTrue();
    expect(File::get($action))->toContain('use App\Domains\Auth\User\DTOs\CreateUserData;');
    expect(File::exists($data))->toBeTrue();
    expect(File::get($data))->toContain('namespace App\Domains\Auth\User\DTOs;');
});

it('follows a remapped action folder name without crashing', function () {
    config()->set('foundry.domains.subdirectories.action', 'UseCases');
    $this->app->forgetInstance(DomainNamespace::class);

    $this->artisan('make:action', ['name' => 'CreateUser', '--domain' => 'Auth', '--subdomain' => 'User'])
        ->assertExitCode(0);

    $action = $this->generatedPath('Domains/Auth/User/UseCases/CreateUser.php');
    $data = $this->generatedPath('Domains/Auth/User/Data/CreateUserData.php');

    expect(File::exists($action))->toBeTrue();
    expect(File::get($action))->toContain('namespace App\Domains\Auth\User\UseCases;');
    expect(File::get($action))->toContain('use App\Domains\Auth\User\Data\CreateUserData;');
    expect(File::exists($data))->toBeTrue();
});

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
