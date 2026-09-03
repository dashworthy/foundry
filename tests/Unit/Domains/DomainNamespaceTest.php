<?php

use Dashworthy\Foundry\Domains\DomainNamespace;
use Dashworthy\Foundry\Domains\Exceptions\DomainOptionException;

function resolver(): DomainNamespace
{
    return new DomainNamespace('Domains', [
        'request' => 'FormRequests',
        'resource' => 'JsonResources',
    ]);
}

it('exposes domain and subdomain options', function () {
    $names = array_map(fn ($o) => $o->getName(), resolver()->optionDefinitions());

    expect($names)->toBe(['domain', 'subdomain']);
});

it('maps folders from config with a pluralized fallback', function () {
    expect(resolver()->folderFor('make:request'))->toBe('FormRequests')
        ->and(resolver()->folderFor('make:resource'))->toBe('JsonResources')
        ->and(resolver()->folderFor('make:model'))->toBe('Models')
        ->and(resolver()->folderFor('make:policy'))->toBe('Policies');
});

it('builds a domain namespace without a class name', function () {
    expect(resolver()->namespaceFor('App', 'billing', 'invoice', 'Models'))
        ->toBe('App\Domains\Billing\Invoice\Models');
});

it('studlies domain/subdomain segments and trims the root namespace', function () {
    expect(resolver()->namespaceFor('App\\', 'auth', 'user', 'FormRequests'))
        ->toBe('App\Domains\Auth\User\FormRequests');
});

it('marks both options as value-required', function () {
    foreach (resolver()->optionDefinitions() as $option) {
        expect($option->isValueRequired())->toBeTrue();
    }
});

it('does not throw when subdomain is present but domain is absent', function () {
    resolver()->assertSubdomainPresent(null, 'User');
    expect(true)->toBeTrue();
});

it('throws when domain is present without subdomain', function () {
    resolver()->assertSubdomainPresent('Auth', null);
})->throws(DomainOptionException::class, 'The --subdomain option is required when --domain is provided.');

it('does not throw when neither domain nor subdomain is present', function () {
    resolver()->assertSubdomainPresent(null, null);

    expect(true)->toBeTrue();
});
