<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Console\Commands;

use Illuminate\Console\GeneratorCommand;

/**
 * Generates the one `{Name}Data` object an action or query takes. `make:action`
 * and `make:query` both call this so their input class is scaffolded in the same
 * shape, from the same stub, in one place.
 *
 * Abstract + `final` concrete (see MakeDataCommand) so `dashworthy/domains` can
 * swap in a domain-aware subclass that places the Data class inside a domain
 * directory, the same way it overrides the action and query generators.
 */
abstract class DataMakeCommand extends GeneratorCommand
{
    protected $name = 'make:data';

    protected $description = 'Create a new data class';

    protected $type = 'Data';

    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/data.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Data';
    }
}
