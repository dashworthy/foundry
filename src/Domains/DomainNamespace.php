<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains;

use Dashworthy\Foundry\Domains\Exceptions\DomainOptionException;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class DomainNamespace
{
    /**
     * @param  array<string, string>  $subdirectories
     */
    public function __construct(
        protected string $base = 'Domains',
        protected array $subdirectories = [],
    ) {}

    /**
     * @return array<int, InputOption>
     */
    public function optionDefinitions(): array
    {
        return [
            new InputOption('domain', null, InputOption::VALUE_REQUIRED, 'The domain the generated class belongs to.'),
            new InputOption('subdomain', null, InputOption::VALUE_REQUIRED, 'The subdomain within the domain.'),
        ];
    }

    public function folderFor(string $commandName): string
    {
        $type = Str::of($commandName)->after('make:')->toString();

        return $this->subdirectories[$type] ?? Str::studly(Str::plural($type));
    }

    public function assertSubdomainPresent(?string $domain, ?string $subdomain): void
    {
        if ($this->filled($domain) && ! $this->filled($subdomain)) {
            throw new DomainOptionException('The --subdomain option is required when --domain is provided.');
        }
    }

    public function namespaceFor(string $rootNamespace, string $domain, string $subdomain, string $folder): string
    {
        return implode('\\', [
            trim($rootNamespace, '\\'),
            trim($this->base, '\\'),
            Str::studly($domain),
            Str::studly($subdomain),
            trim($folder, '\\'),
        ]);
    }

    protected function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
