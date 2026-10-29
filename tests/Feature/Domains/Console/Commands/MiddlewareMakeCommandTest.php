<?php

use Illuminate\Support\Facades\File;

it('places a middleware into its domain folder', function () {
    $this->artisan('make:middleware', ['name' => 'EnsureOrderOwner', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Middleware/EnsureOrderOwner.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Middleware;');
});
