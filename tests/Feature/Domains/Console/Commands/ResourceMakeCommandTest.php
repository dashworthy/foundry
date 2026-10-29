<?php

use Illuminate\Support\Facades\File;

it('places a resource into the configured JsonResources folder of its domain', function () {
    $this->artisan('make:resource', ['name' => 'OrderResource', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/JsonResources/OrderResource.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\JsonResources;');
});
