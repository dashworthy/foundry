<?php

declare(strict_types=1);

use Dashworthy\Foundry\FoundryServiceProvider;

it('registers the service provider', function () {
    expect(app()->getLoadedProviders())->toHaveKey(FoundryServiceProvider::class);
});
