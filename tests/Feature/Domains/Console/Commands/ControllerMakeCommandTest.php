<?php

use Illuminate\Support\Facades\File;

it('places a controller into its domain folder', function () {
    $this->artisan('make:controller', ['name' => 'OrderController', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Controllers/OrderController.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Controllers;');
});
