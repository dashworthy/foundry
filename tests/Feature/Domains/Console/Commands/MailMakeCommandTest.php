<?php

use Illuminate\Support\Facades\File;

it('places a mail into its domain folder', function () {
    $this->artisan('make:mail', ['name' => 'OrderReceipt', '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Sales/Order/Mail/OrderReceipt.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('namespace App\Domains\Sales\Order\Mail;');
});
