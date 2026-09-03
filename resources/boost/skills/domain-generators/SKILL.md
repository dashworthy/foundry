---
name: domain-generators
description: >
  Use when generating classes into the app/Domains DDD structure with dashworthy/foundry.
  Activates when running make: commands with --domain/--subdomain, mentioning
  Domains/subdomains, make:action/make:query/make:data/make:precondition, or asking where a
  generated class should live.
license: MIT
metadata:
  author: Andrew Leach
---

# Domain-Driven Generators

`dashworthy/foundry` layers domain-aware placement onto Laravel's `make:*` generators so a
generated class lands in a Domain-Driven structure instead of the stock location:

```
app/Domains/{Domain}/{Subdomain}/{Folder}/{Class}.php
```

## Usage

Pass `--domain` and `--subdomain` to any overridden `make:` generator. Both are required
together — `--subdomain` is mandatory when `--domain` is given.

```bash
php artisan make:action     --domain=Billing --subdomain=Invoice PublishInvoice
php artisan make:query      --domain=Billing --subdomain=Invoice OverdueInvoices
php artisan make:model      --domain=Billing --subdomain=Invoice Invoice
php artisan make:controller --domain=Billing --subdomain=Invoice InvoiceController
php artisan make:request    --domain=Billing --subdomain=Invoice StoreInvoiceRequest
```

Omit the flags for stock Laravel placement (`app/Models/…`, etc.).

Running a Foundry generator interactively without the flags prompts for the domain and
subdomain (pick an existing one, create a new one, or choose none). Pass
`--no-interaction` and the flags are required — without them the command exits with an
error rather than guessing.

## Overridden generators

Foundry's own generators — `make:action`, `make:query`, `make:data`, `make:precondition` —
plus the Laravel generators: `make:` model, controller, request, resource, policy, enum,
notification, job, event, listener, rule, observer, cast, mail, middleware.
`make:migration` is intentionally not domain-scoped.

When `make:action` or `make:query` runs with `--domain`/`--subdomain`, the `{Name}Data` it
scaffolds is placed in the same domain's `Data/` folder.

## Folder names

Folder names come from `config/foundry.php` `domains.subdirectories` (e.g. `request` →
`FormRequests`, `resource` → `JsonResources`). An unmapped or custom generator falls back
to the pluralized command type. Publish the config to customise it:

```bash
php artisan vendor:publish --tag=foundry-config
```

## Opting a custom command in

A custom generator becomes domain-aware by using the trait:

```php
use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;
use Illuminate\Console\GeneratorCommand;

class MakeThingCommand extends GeneratorCommand
{
    use InteractsWithDomains;
    // ...
}
```

## Known limitation

Compound generator flags (e.g. `make:model -mcr`) place the primary class into the domain;
secondary artifacts derive from the base name and may nest differently. Generate related
classes with their own `make:` calls for precise placement.
