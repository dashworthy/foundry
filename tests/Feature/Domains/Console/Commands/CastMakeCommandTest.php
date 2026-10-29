<?php

use Illuminate\Support\Facades\File;

it('places a cast into its domain folder', function () {
    $this->artisan('make:cast', ['name' => 'MoneyCast', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Casts/MoneyCast.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Casts;');
});
