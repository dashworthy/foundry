<?php

declare(strict_types=1);

namespace Dashworthy\Foundry;

use Dashworthy\Foundry\Console\Commands\MakeActionCommand;
use Dashworthy\Foundry\Console\Commands\MakeDataCommand;
use Dashworthy\Foundry\Console\Commands\MakePreconditionCommand;
use Dashworthy\Foundry\Console\Commands\MakeQueryCommand;
use Dashworthy\Foundry\Domains\Console\Commands as Domain;
use Dashworthy\Foundry\Domains\DomainNamespace;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\CastMakeCommand;
use Illuminate\Foundation\Console\EnumMakeCommand;
use Illuminate\Foundation\Console\EventMakeCommand;
use Illuminate\Foundation\Console\JobMakeCommand;
use Illuminate\Foundation\Console\ListenerMakeCommand;
use Illuminate\Foundation\Console\MailMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Foundation\Console\NotificationMakeCommand;
use Illuminate\Foundation\Console\ObserverMakeCommand;
use Illuminate\Foundation\Console\PolicyMakeCommand;
use Illuminate\Foundation\Console\RequestMakeCommand;
use Illuminate\Foundation\Console\ResourceMakeCommand;
use Illuminate\Foundation\Console\RuleMakeCommand;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Routing\Console\MiddlewareMakeCommand;
use Illuminate\Support\ServiceProvider;

class FoundryServiceProvider extends ServiceProvider
{
    /**
     * Register the generator singletons, the domain config, and the domain
     * namespace resolver.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/foundry.php', 'foundry');

        // Bound by class name so boot() can swap in the domain-aware subclasses
        // with $this->app->extend(), the same way it replaces Laravel's core
        // generators.
        $this->app->singleton(MakeActionCommand::class);
        $this->app->singleton(MakeQueryCommand::class);
        $this->app->singleton(MakeDataCommand::class);
        $this->app->singleton(MakePreconditionCommand::class);

        $this->app->singleton(DomainNamespace::class, function (): DomainNamespace {
            $base = config('foundry.domains.base', 'Domains');

            return new DomainNamespace(
                base: is_string($base) ? $base : 'Domains',
                subdirectories: $this->stringMap(config('foundry.domains.subdirectories', [])),
            );
        });
    }

    /**
     * Coerce a config value into a plain string-keyed, string-valued map,
     * dropping any entry that is not — so the domain resolver always receives
     * the array<string, string> its constructor promises.
     *
     * @return array<string, string>
     */
    protected function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**
     * Register the generator commands, publish the domain config, and layer the
     * domain-aware placement onto every `make:*` generator.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeActionCommand::class,
            MakeQueryCommand::class,
            MakeDataCommand::class,
            MakePreconditionCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/foundry.php' => config_path('foundry.php'),
        ], 'foundry-config');

        foreach ($this->commandOverrides() as $core => $override) {
            if ($this->app->bound($core)) {
                $this->app->extend($core, fn (object $command, Application $app) => $app->make($override));
            }
        }
    }

    /**
     * Map each core generator command to its domain-aware subclass.
     *
     * @return array<class-string, class-string>
     */
    protected function commandOverrides(): array
    {
        return [
            MakeActionCommand::class => Domain\MakeActionCommand::class,
            MakeQueryCommand::class => Domain\MakeQueryCommand::class,
            MakeDataCommand::class => Domain\MakeDataCommand::class,
            MakePreconditionCommand::class => Domain\MakePreconditionCommand::class,
            ModelMakeCommand::class => Domain\ModelMakeCommand::class,
            RequestMakeCommand::class => Domain\RequestMakeCommand::class,
            ResourceMakeCommand::class => Domain\ResourceMakeCommand::class,
            ControllerMakeCommand::class => Domain\ControllerMakeCommand::class,
            PolicyMakeCommand::class => Domain\PolicyMakeCommand::class,
            EnumMakeCommand::class => Domain\EnumMakeCommand::class,
            NotificationMakeCommand::class => Domain\NotificationMakeCommand::class,
            JobMakeCommand::class => Domain\JobMakeCommand::class,
            EventMakeCommand::class => Domain\EventMakeCommand::class,
            ListenerMakeCommand::class => Domain\ListenerMakeCommand::class,
            RuleMakeCommand::class => Domain\RuleMakeCommand::class,
            ObserverMakeCommand::class => Domain\ObserverMakeCommand::class,
            CastMakeCommand::class => Domain\CastMakeCommand::class,
            MailMakeCommand::class => Domain\MailMakeCommand::class,
            MiddlewareMakeCommand::class => Domain\MiddlewareMakeCommand::class,
        ];
    }
}
