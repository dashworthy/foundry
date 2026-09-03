<?php

it('boots the package and merges config defaults', function () {
    expect(config('foundry.domains.base'))->toBe('Domains')
        ->and(config('foundry.domains.subdirectories.request'))->toBe('FormRequests');
});
