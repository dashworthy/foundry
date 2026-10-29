<?php

use Illuminate\Support\Facades\File;

it('places a event into its domain folder', function () {
    $this->artisan('make:event', ['name' => 'OrderPlaced', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Events/OrderPlaced.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Events;');
});
