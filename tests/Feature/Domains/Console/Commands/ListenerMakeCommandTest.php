<?php

use Illuminate\Support\Facades\File;

it('places a listener into its domain folder', function () {
    $this->artisan('make:listener', ['name' => 'SendOrderEmail', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Listeners/SendOrderEmail.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Listeners;');
});
