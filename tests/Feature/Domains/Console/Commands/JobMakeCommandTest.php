<?php

use Illuminate\Support\Facades\File;

it('places a job into its domain folder', function () {
    $this->artisan('make:job', ['name' => 'ProcessOrder', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Jobs/ProcessOrder.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Jobs;');
});
