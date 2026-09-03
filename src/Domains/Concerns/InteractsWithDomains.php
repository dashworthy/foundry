<?php

namespace Dashworthy\Foundry\Domains\Concerns;

use Dashworthy\Foundry\Domains\DomainNamespace;
use Dashworthy\Foundry\Domains\Exceptions\DomainOptionException;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait InteractsWithDomains
{
    /**
     * Sentinel written back onto the resolved --domain option when the user
     * interactively opts out of a domain ("None").
     *
     * `dashworthy/foundry`' `ActionMakeCommand::handle()` forwards this
     * command's --domain/--subdomain values to a nested `make:data`
     * call via `$this->call()`, keeping any non-null value. Leaving --domain
     * genuinely null after "None" is chosen would make that nested call
     * (itself domain-aware once dashworthy/domains overrides make:data)
     * see an absent --domain and try to prompt a second time — but the
     * nested command's input has no stream attached, so the prompt fails
     * immediately. Writing this marker back gives the nested call a non-null
     * value that both resolveDomainSelection() and getDefaultNamespace()
     * recognize as "already decided: no domain", so it reuses the same
     * stock-placement decision instead of re-prompting.
     */
    private const string NO_DOMAIN_MARKER = '__dashworthy_domains_none__';

    /**
     * Merge the --domain / --subdomain options into the command's options.
     *
     * Only consulted for commands that declare $name. Commands declaring
     * $signature never reach this — see configure().
     *
     * @return array<array-key, mixed>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), $this->domainResolver()->optionDefinitions());
    }

    /**
     * Add the domain options directly to the resolved definition.
     *
     * getOptions() alone is not enough: Laravel's Command::__construct only
     * calls it when the command declares $name. A command declaring $signature
     * takes the configureUsingFluentDefinition() branch, which parses the
     * signature and never consults getOptions() — so the options were silently
     * dropped and --domain did not exist. Adding them here works for both
     * styles, and the guard keeps $name-based commands from double-adding.
     */
    protected function configure(): void
    {
        parent::configure();

        foreach ($this->domainResolver()->optionDefinitions() as $option) {
            if (! $this->getDefinition()->hasOption($option->getName())) {
                $this->getDefinition()->addOption($option);
            }
        }
    }

    /**
     * Resolve the domain/subdomain placement (interactively when possible),
     * then hand off to the core generator.
     *
     * Declared `?bool` (rather than left untyped) because `dashworthy/foundry`'
     * `ActionMakeCommand::handle()` declares `?bool`, and a trait method
     * overriding a typed parent method must be return-type compatible with it.
     * The console kernel casts a handler's return to int for the exit code: a
     * truthy return is a non-zero (failure) code, so `true` on a resolution
     * failure yields exit 1, while `null` after a clean run yields exit 0.
     *
     * `parent::handle()` is called for its side effect (writing the class) and
     * its result deliberately discarded — the core generators disagree on that
     * return type (some are `void`), and placement failures already surface via
     * resolveDomainSelection() before it runs.
     */
    public function handle(): ?bool
    {
        if (! $this->resolveDomainSelection()) {
            return true;
        }

        parent::handle();

        return null;
    }

    /**
     * Ensure the domain/subdomain options are populated, prompting the user
     * when the command is running interactively.
     *
     * @return bool False when the selection could not be resolved (already errored).
     */
    protected function resolveDomainSelection(): bool
    {
        $domain = $this->trimmedOption('domain');

        if ($domain === self::NO_DOMAIN_MARKER) {
            return true; // Already decided "no domain" by an outer command call.
        }

        if ($domain === null) {
            if (! $this->input->isInteractive()) {
                $this->components->error('The --domain and --subdomain options are required when running non-interactively.');

                return false;
            }

            $domain = $this->promptForDomain();

            if ($domain === null) {
                $this->input->setOption('domain', self::NO_DOMAIN_MARKER);

                return true; // "None" → stock placement.
            }

            $this->input->setOption('domain', $domain);
        }

        if ($this->trimmedOption('subdomain') === null) {
            if (! $this->input->isInteractive()) {
                try {
                    $this->domainResolver()->assertSubdomainPresent($domain, null);
                } catch (DomainOptionException $e) {
                    $this->components->error($e->getMessage());

                    return false;
                }
            }

            $this->input->setOption('subdomain', $this->promptForSubdomain($domain));
        }

        return true;
    }

    /**
     * Prompt for the domain, offering existing domains plus create/none choices.
     *
     * @return string|null Null when the user opts out of a domain (stock placement).
     */
    protected function promptForDomain(): ?string
    {
        $existing = $this->existingDomains();
        $options = array_combine($existing, $existing);
        $options['__new__'] = 'Create a new domain…';
        $options['__none__'] = 'None (no domain — stock location)';

        $choice = select(
            label: 'Which domain should this class belong to?',
            options: $options,
            default: $existing[0] ?? '__new__',
        );

        return match ($choice) {
            '__none__' => null,
            '__new__' => text(label: 'New domain name', required: true),
            default => (string) $choice,
        };
    }

    /**
     * Prompt for a subdomain scoped to the chosen domain (always required).
     */
    protected function promptForSubdomain(string $domain): string
    {
        $existing = $this->existingSubdomains($domain);
        $options = array_combine($existing, $existing);
        $options['__new__'] = 'Create a new subdomain…';

        $choice = select(
            label: "Which subdomain within {$domain}?",
            options: $options,
            default: $existing[0] ?? '__new__',
        );

        return $choice === '__new__'
            ? text(label: 'New subdomain name', required: true)
            : (string) $choice;
    }

    /**
     * The sorted list of existing domain directory names under the base folder.
     *
     * @return array<int, string>
     */
    protected function existingDomains(): array
    {
        return $this->directoryNames($this->domainsBasePath());
    }

    /**
     * The sorted list of existing subdomain directory names within a domain.
     *
     * @return array<int, string>
     */
    protected function existingSubdomains(string $domain): array
    {
        return $this->directoryNames($this->domainsBasePath().'/'.$domain);
    }

    /**
     * Read the trimmed value of an option, treating blank/empty strings as absent.
     */
    protected function trimmedOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    protected function domainResolver(): DomainNamespace
    {
        return app()->bound(DomainNamespace::class)
            ? app(DomainNamespace::class)
            : new DomainNamespace;
    }

    /**
     * Place the generated class inside its domain when --domain is provided.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $domain = $this->trimmedOption('domain');

        if ($domain === null || $domain === self::NO_DOMAIN_MARKER) {
            return parent::getDefaultNamespace($rootNamespace);
        }

        $resolver = $this->domainResolver();

        return $resolver->namespaceFor(
            $rootNamespace,
            $domain,
            $this->trimmedOption('subdomain') ?? '',
            $resolver->folderFor($this->getName() ?? ''),
        );
    }

    /**
     * Absolute path to the application's domains base directory.
     */
    protected function domainsBasePath(): string
    {
        $base = config('domains.base', 'Domains');

        return app_path(is_string($base) ? $base : 'Domains');
    }

    /**
     * Sorted subdirectory basenames of the given path (empty when it is absent).
     *
     * @return array<int, string>
     */
    protected function directoryNames(string $path): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $directories = array_filter(File::directories($path), 'is_string');
        $names = array_map('basename', $directories);
        sort($names);

        return $names;
    }
}
