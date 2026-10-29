<?php

use Illuminate\Support\Facades\File;

it('places a observer into its domain folder', function () {
    $this->artisan('make:observer', ['name' => 'OrderObserver', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Observers/OrderObserver.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Observers;');
});
