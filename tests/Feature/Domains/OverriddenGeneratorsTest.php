<?php

use Illuminate\Support\Facades\File;

dataset('overridden_generators', [
    'controller' => ['make:controller', 'Controllers', 'OrderController'],
    'policy' => ['make:policy', 'Policies', 'OrderPolicy'],
    'enum' => ['make:enum', 'Enums', 'OrderStatus'],
    'notification' => ['make:notification', 'Notifications', 'OrderShipped'],
    'job' => ['make:job', 'Jobs', 'ProcessOrder'],
    'event' => ['make:event', 'Events', 'OrderPlaced'],
    'listener' => ['make:listener', 'Listeners', 'SendOrderEmail'],
    'rule' => ['make:rule', 'Rules', 'ValidOrder'],
    'observer' => ['make:observer', 'Observers', 'OrderObserver'],
    'cast' => ['make:cast', 'Casts', 'MoneyCast'],
    'mail' => ['make:mail', 'Mail', 'OrderReceipt'],
    'middleware' => ['make:middleware', 'Middleware', 'EnsureOrderOwner'],
]);

it('places each overridden generator into its domain folder', function (string $command, string $folder, string $class) {
    $this->artisan($command, ['name' => $class, '--domain' => 'Sales', '--subdomain' => 'Order'])
        ->assertExitCode(0);

    $path = $this->generatedPath("Domains/Sales/Order/{$folder}/{$class}.php");
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain("namespace App\Domains\Sales\Order\\{$folder};");
})->with('overridden_generators');
