<?php

use Dashworthy\Foundry\Domains\DomainNamespace;
use Dashworthy\Foundry\Domains\Exceptions\DomainOptionException;

it('is raised for a domain without a subdomain and can be caught as an invalid argument', function () {
    $namespace = new DomainNamespace('Domains', []);

    expect(fn () => $namespace->assertSubdomainPresent('Billing', '  '))
        ->toThrow(InvalidArgumentException::class, 'The --subdomain option is required when --domain is provided.');

    expect(new DomainOptionException('bad option'))->toBeInstanceOf(InvalidArgumentException::class);
});
