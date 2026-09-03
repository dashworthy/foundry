<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Console\Commands;

use Illuminate\Console\GeneratorCommand;

/**
 * Laravel core ships no `make:query`, so unlike a generator that overrides an
 * existing Foundation command this class defines the stock default namespace
 * itself. Domain-aware subclasses mix in a trait whose `getDefaultNamespace()`
 * calls `parent::getDefaultNamespace()` to reach it — so a subclass must never
 * redeclare that method, since a method on the class always beats a same-named
 * trait method and would silently disable domain support.
 */
abstract class QueryMakeCommand extends GeneratorCommand
{
    protected $name = 'make:query';

    protected $description = 'Create a new query class and its data';

    protected $type = 'Query';

    /**
     * A query without a Data object does not run, so the pair is always
     * scaffolded together.
     */
    public function handle(): ?bool
    {
        $created = parent::handle();

        if ($created === false) {
            return false;
        }

        $this->call('make:data', array_filter([
            'name' => $this->getNameInput().'Data',
            '--domain' => $this->input->hasOption('domain') ? $this->option('domain') : null,
            '--subdomain' => $this->input->hasOption('subdomain') ? $this->option('subdomain') : null,
        ], static fn (mixed $value): bool => $value !== null));

        return $created;
    }

    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/query.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Queries';
    }

    /**
     * Fill in {{ dataNamespace }} so the generated query's `use` statement for
     * its `{Name}Data` points at the namespace the nested `make:data` call (see
     * handle()) actually generates it into.
     *
     * @param  string  $name  Fully-qualified class name, as passed by GeneratorCommand::handle().
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return str_replace('{{ dataNamespace }}', $this->dataNamespace($name), $stub);
    }

    /**
     * Stock placement mirrors DataMakeCommand::getDefaultNamespace() directly,
     * rather than inferring it from this query's own namespace, so the two
     * cannot drift. `protected` so the domain-aware override in
     * `dashworthy/domains` can replace it wholesale instead of this class having
     * to guess at domain placement.
     */
    protected function dataNamespace(string $name): string
    {
        return trim($this->rootNamespace(), '\\').'\Data';
    }
}
