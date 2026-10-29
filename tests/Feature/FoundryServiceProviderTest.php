<?php

declare(strict_types=1);

use Dashworthy\Foundry\FoundryServiceProvider;

it('registers the service provider', function () {
    expect(app()->getLoadedProviders())->toHaveKey(FoundryServiceProvider::class);
});

it('boots the package and merges config defaults', function () {
    expect(config('foundry.domains.base'))->toBe('Domains')
        ->and(config('foundry.domains.subdirectories.request'))->toBe('FormRequests');
});
