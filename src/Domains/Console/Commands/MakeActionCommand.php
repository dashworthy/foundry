<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains\Console\Commands;

use Dashworthy\Foundry\Console\Commands\ActionMakeCommand;
use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;

class MakeActionCommand extends ActionMakeCommand
{
    use InteractsWithDomains;

    /**
     * Place the generated action's `use App\...\{Name}Data;` import at wherever
     * the nested `make:data` call (see ActionMakeCommand::handle()) will
     * actually generate the data class, rather than letting the parent guess
     * from this action's own namespace.
     *
     * This package owns domain placement and has the resolver, so it can
     * compute the answer authoritatively from the `domains.subdirectories`
     * config map instead of inferring it. Passing the literal command name
     * `'make:data'` to `folderFor()` is the key point: it looks up `'data'` in
     * that map, so a user remapping that entry is followed correctly,
     * independent of whatever `'action'` maps to.
     *
     * By the time this runs (from buildClass(), reached via
     * GeneratorCommand::handle(), reached via InteractsWithDomains::handle()
     * only after resolveDomainSelection() has already run), --domain and
     * --subdomain are fully resolved — never mid-prompt.
     */
    protected function dataNamespace(string $name): string
    {
        $domain = $this->trimmedOption('domain');

        if ($domain === null || $domain === self::NO_DOMAIN_MARKER) {
            return parent::dataNamespace($name);
        }

        $resolver = $this->domainResolver();

        return $resolver->namespaceFor(
            $this->rootNamespace(),
            $domain,
            $this->trimmedOption('subdomain') ?? '',
            $resolver->folderFor('make:data'),
        );
    }
}
