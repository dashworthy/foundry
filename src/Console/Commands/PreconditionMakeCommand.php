<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Console\Commands;

use Illuminate\Console\GeneratorCommand;

abstract class PreconditionMakeCommand extends GeneratorCommand
{
    protected $name = 'make:precondition';

    protected $description = 'Create a new precondition class';

    protected $type = 'Precondition';

    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/precondition.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Preconditions';
    }
}
