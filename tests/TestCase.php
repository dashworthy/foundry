<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests;

use Closure;
use Dashworthy\Foundry\FoundryServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $appPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Point the generators at an isolated, disposable app directory and give
        // it the `App` root namespace, so `make:action`/`make:query` write
        // `App\Actions\...` into a temp dir the tests can assert on and tearDown
        // can delete — never into the Testbench skeleton.
        $this->appPath = sys_get_temp_dir().'/dashworthy-foundry-'.uniqid();
        File::ensureDirectoryExists($this->appPath);
        $this->app->useAppPath($this->appPath);

        // A stock Laravel skeleton ships with app/Models, and
        // ModelMakeCommand::getDefaultNamespace() only appends "\Models" to the
        // stock (non-domain) namespace when that directory already exists.
        // Recreate it so the domain-aware make:model override's stock fallback
        // matches a real application instead of landing at the app root.
        File::ensureDirectoryExists($this->appPath.'/Models');

        Closure::bind(function () {
            $this->namespace = 'App';
        }, $this->app, get_class($this->app))();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->appPath);

        parent::tearDown();
    }

    protected function generatedPath(string $relative): string
    {
        return $this->appPath.'/'.ltrim($relative, '/');
    }

    protected function getPackageProviders($app): array
    {
        return [
            FoundryServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
