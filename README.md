<div align="center">
    <h1>Actions</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/dashworthy/actions"><img src="https://img.shields.io/packagist/v/dashworthy/actions.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/dashworthy/actions"><img src="https://img.shields.io/packagist/php-v/dashworthy/actions.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/dashworthy/actions"><img src="https://badge.laravel.cloud/badge/dashworthy/actions?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/dashworthy/actions/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/dashworthy/actions/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/dashworthy/actions"><img src="https://img.shields.io/packagist/dt/dashworthy/actions.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Single-argument actions with reusable preconditions and owned transactions for Laravel.

## Installation

You can install the package via Composer:

```bash
composer require dashworthy/actions
```

## Usage

### Defining params

An action takes a single argument: a `final readonly` DTO implementing `ActionParams`. Implement `HasActor` and/or `HasTarget` when a precondition needs to inspect who is acting or what they're acting on.

```php
use Dashworthy\Actions\Contracts\ActionParams;
use Dashworthy\Actions\Contracts\HasActor;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class PublishPostActionParams implements ActionParams, HasActor
{
    public function __construct(
        private Authenticatable $user,
        public Post $post,
    ) {}

    public function actor(): Authenticatable
    {
        return $this->user;
    }
}
```

### Defining an action

Extend `Action`, list preconditions cheapest/most-restrictive first, and implement `handle()`. `handle()` runs inside a database transaction by default.

```php
use Dashworthy\Actions\Action;

/**
 * Not `final`: subclassing or mocking in tests needs to reach in without
 * fighting the class declaration.
 *
 * @extends Action<PublishPostActionParams, Post>
 */
class PublishPost extends Action
{
    protected function preconditions(): array
    {
        return [
            new ActorOwnsPost,
            new PostHasContent,
        ];
    }

    protected function handle(PublishPostActionParams $params): Post
    {
        $params->post->update(['published_at' => now()]);

        return $params->post;
    }
}
```

`handle()` takes your own `{Name}ActionParams`, not the `ActionParams` marker interface. `Action` gets that by declaring `handle()` as a `@method` annotation rather than a real abstract method: PHP forbids narrowing a parameter type in an override, so a real `abstract protected function handle(ActionParams $params)` would fatal any subclass that named its actual params class. The trade is that an action which never declares `handle()` fails on its first `execute()` rather than at class-declaration time — worth asserting in an architecture test over your own actions:

```php
it('has every action declaring handle()', function () {
    // ...reflect over your Action subclasses and assert hasMethod('handle')
});
```

### Preconditions

A precondition is a pipe in a Laravel [`Illuminate\Pipeline\Pipeline`](https://laravel.com/docs/helpers#pipeline) — the same chain-of-responsibility convention Laravel's own HTTP middleware uses. It needs no package interface, just a `handle($params, Closure $next)` method: call `$next($params)` to let the chain continue, or throw to refuse. The exception is whatever the application wants — this package doesn't ship one and doesn't wrap it.

```php
use Closure;

final class ActorOwnsPost
{
    public function handle(PublishPostActionParams $params, Closure $next): mixed
    {
        if ($params->post->user_id !== $params->actor()->getAuthIdentifier()) {
            throw new NotPostOwner('You do not own this post.');
        }

        return $next($params);
    }
}
```

`preconditions()` returns a list of pipe instances, or class-strings the Pipeline resolves from the container:

```php
protected function preconditions(): array
{
    return [
        new ActorOwnsPost,
        PostHasContent::class, // resolved from the container when the chain runs
    ];
}
```

**No static type checking between a precondition and the params it runs against.** Earlier versions had every precondition `@implements Precondition<TParams>`, so PHPStan could reject an action declaring a precondition whose params type it couldn't satisfy. A pipe is a plain object with a `handle()` method and no shared interface to template against, so that check no longer exists — a precondition with the wrong params type now fails at runtime (a `TypeError` the moment the Pipeline calls `handle()`, or worse, a silent bug if you left it `mixed`) rather than at analysis time. `Action` still declares `@extends Action<TParams, TReturn>`, so `handle()`'s own params/return typing is unaffected.

### Running an action

```php
$post = (new PublishPost)->execute(new PublishPostActionParams($user, $post));
```

`execute()` always does the same two things, in order, with no way for a subclass to reorder them: assert preconditions, then run `handle()` inside a transaction. Preconditions run in declaration order and the chain stops at the first that throws — whatever exception it throws propagates unchanged, before `handle()` ever runs.

The package does not dispatch events for you. If a side effect must not fire until the work commits, use Laravel's own tools: `ShouldQueueAfterCommit` on the notification or job, or a `DB::afterCommit()` call from inside `handle()`.

To ask whether an action would be allowed — for deciding whether a control renders — without running it:

```php
if ($action->permits($params)) {
    // show the "Publish" button
}
```

`permits()` runs the precondition chain and returns `false` if anything throws, `true` otherwise. It never runs `handle()`.

### Handling refusals

There is nothing to configure. A precondition throws whatever exception the application defines — a domain exception, a Laravel `AuthorizationException`, anything — and `assert()`/`execute()`/`permits()` never touch it beyond letting it propagate (or, for `permits()`, catching it to answer `false`). Catch it wherever that decision belongs — typically the app's own exception handler:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (NotPostOwner $e, Request $request) {
        return back()->withErrors(['action' => $e->getMessage()]);
    });
})
```

### Rolling back on failure

The transaction already undoes any database writes when `handle()` throws. It cannot undo anything else `handle()` did — an external charge, a third-party API call. Override `rollback()` for that:

```php
/**
 * @extends Action<ChargeCardActionParams, Charge>
 */
class ChargeCard extends Action
{
    private ?string $chargeId = null;

    protected function handle(ChargeCardActionParams $params): Charge
    {
        $charge = $this->gateway->charge($params->amount);
        $this->chargeId = $charge->id;

        // ... more work that might throw ...

        return $charge;
    }

    protected function rollback(): void
    {
        if ($this->chargeId !== null) {
            $this->gateway->refund($this->chargeId);
        }
    }
}
```

`rollback()` takes no arguments — unlike `handle()` it has a body to override, so narrowing a params type on it is the thing PHP forbids. Capture whatever the rollback needs, the params included, as an instance property during `handle()`.

`rollback()` runs only when `handle()` throws — never for a refused precondition, since nothing has happened yet to undo. If `rollback()` itself throws, that's reported and swallowed; the exception that caused the rollback is always what propagates. It fires for this action's own instance only: a nested action's `rollback()` is not invoked automatically when an ancestor fails after that nested action already returned successfully — compose that yourself if you need it.

### Generators

```bash
php artisan make:action PublishPost
php artisan make:action-params PublishPostActionParams
php artisan make:precondition ActorOwnsPost
```

`make:action` also scaffolds the matching `{Name}ActionParams` for you — reach for `make:action-params` directly only when you need a params class on its own (e.g. sharing one between actions). All three commands are domain-aware wherever `dashworthy/domains` is installed, the same way Laravel's own generators are.

`make:action` scaffolds the action already extending `Dashworthy\Actions\Action`, with a `handle()` typed against the `{Name}ActionParams` it generates alongside it — nothing to configure, and the pair runs as generated. `make:precondition` scaffolds a pipe whose `handle()` calls `$next($params)`; narrow the `mixed $params` type to the action's own `{Name}ActionParams` and add the refusal check yourself.

### Calling an action from inside a transaction

Nesting `execute()` inside a caller-managed transaction is supported — a plain `DB::transaction()`, another action's `handle()`, whatever:

```php
DB::transaction(function () use ($action, $params) {
    $action->execute($params); // runs normally
});
```

The action's own `DB::transaction()` call becomes a savepoint inside the caller's, exactly as you'd expect. `execute()` doesn't need to own the outermost transaction, so there's no guard here and nothing to restructure the caller around.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Actions! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Credits

- [Andrew Leach](https://github.com/dashworthy)
- [All Contributors](../../contributors)

## License

Actions is open-sourced software licensed under the [MIT license](LICENSE.md).
