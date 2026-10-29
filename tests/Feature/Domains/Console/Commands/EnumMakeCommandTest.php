<?php

use Illuminate\Support\Facades\File;

it('places a enum into its domain folder', function () {
    $this->artisan('make:enum', ['name' => 'OrderStatus', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Enums/OrderStatus.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Enums;');
});
