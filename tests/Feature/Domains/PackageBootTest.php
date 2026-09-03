<?php

it('boots the package and merges config defaults', function () {
    expect(config('domains.base'))->toBe('Domains')
        ->and(config('domains.subdirectories.request'))->toBe('FormRequests');
});
