<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains\Console\Commands;

use Dashworthy\Foundry\Console\Commands\QueryMakeCommand;
use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;

class MakeQueryCommand extends QueryMakeCommand
{
    use InteractsWithDomains;

    /**
     * Point the generated query's `use App\...\{Name}Data;` import at wherever
     * the nested `make:data` call (see QueryMakeCommand::handle()) will actually
     * generate the data class, rather than letting the parent derive it from the
     * stock root namespace.
     *
     * Mirrors the action override: passing the literal `'make:data'` to
     * folderFor() looks up `'data'` in the `domains.subdirectories` map, so a
     * user remapping that entry is followed correctly. By the time this runs,
     * --domain and --subdomain are fully resolved by
     * InteractsWithDomains::handle().
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
