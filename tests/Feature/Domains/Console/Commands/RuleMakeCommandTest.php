<?php

use Illuminate\Support\Facades\File;

it('places a rule into its domain folder', function () {
    $this->artisan('make:rule', ['name' => 'ValidOrder', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Rules/ValidOrder.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Rules;');
});
