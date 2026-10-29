<?php

use Illuminate\Support\Facades\File;

it('places a notification into its domain folder', function () {
    $this->artisan('make:notification', ['name' => 'OrderShipped', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Notifications/OrderShipped.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Notifications;');
});
