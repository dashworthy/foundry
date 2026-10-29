<?php

use Illuminate\Support\Facades\File;

it('places a policy into its domain folder', function () {
    $this->artisan('make:policy', ['name' => 'OrderPolicy', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Policies/OrderPolicy.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Policies;');
});
