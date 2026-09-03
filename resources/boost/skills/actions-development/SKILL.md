---
name: actions-development
description: >
  Define and run dashworthy/actions Actions, ActionParams, and precondition pipes in
  a Laravel application: single-argument actions with reusable preconditions,
  owned transactions, and rollback on failure.
license: MIT
metadata:
  author: Andrew Leach
---

# Actions

Use this skill whenever a Laravel application defines or calls a `dashworthy/actions`
`Action` — creating a new one, adding a precondition, wiring up `execute()`/`permits()`
at a call site, or handling a refusal.

## Primary Goal

- Model a unit of work as an `Action`: one `ActionParams` DTO in, preconditions
  checked before anything runs, `handle()` inside an owned transaction, and
  `rollback()` for anything the transaction can't undo.

## Workflow

### 1. Generate the pieces

```bash
php artisan make:action PublishPost
php artisan make:action-params PublishPostActionParams
php artisan make:precondition ActorOwnsPost
```

`make:action` also scaffolds `PublishPostActionParams` in its own `ActionParams` namespace
(`App\ActionParams` stock, or the domain's `ActionParams/` folder wherever
`dashworthy/domains` is installed) — reach for `make:action-params` on its own only when a
params class needs to exist without a matching action (e.g. shared between actions). Never
hand-write the `@extends` annotation from scratch — copy it from a generated file; PHPStan
depends on it being present and correct.

A generated action already extends `Dashworthy\Actions\Action` with a `handle()` typed
against the `{Name}ActionParams` generated alongside it, so the pair runs as generated.
`make:precondition` scaffolds a pipe whose `handle()` calls `$next($params)`.

### 2. Define the params DTO

A `final readonly` class implementing `ActionParams`. Add `HasActor`/`HasTarget`
only when a precondition needs to read who is acting or what they're acting on:

```php
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

### 3. Write preconditions before writing handle()

A precondition is a pipe in a Laravel `Illuminate\Pipeline\Pipeline` — the same
chain-of-responsibility convention as HTTP middleware. It needs no package interface,
just a `handle($params, Closure $next)` method: call `$next($params)` to continue the
chain, or throw to refuse. One reusable check per class, cheapest/most-restrictive
first — the chain stops at the first that throws, so ordering is a correctness
property, not a style choice:

```php
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

The exception is whatever the application defines — this package ships none and does not
wrap it. There is no compile-time check that a precondition's params type matches the
action's `TParams`: a pipe is a plain object with a `handle()` method, not something that
implements a templated interface, so a mismatched precondition now fails at runtime
instead of at analysis time.

### 4. Implement handle(), add rollback() only if needed

Extend `Dashworthy\Actions\Action` directly — there is no `refusal()` hook to implement,
per-action or on a shared base class. Type `handle()` against the action's own
`{Name}ActionParams`, never the `ActionParams` marker interface, and keep the class
non-`final` so tests can subclass or mock it:

```php
/**
 * @extends Action<PublishPostActionParams, Post>
 */
class PublishPost extends Action
{
    protected function preconditions(): array
    {
        return [new ActorOwnsPost];
    }

    protected function handle(PublishPostActionParams $params): Post
    {
        $params->post->update(['published_at' => now()]);

        return $params->post;
    }
}
```

`Action` declares `handle()` as a `@method` annotation rather than a real abstract method
precisely so this narrowing is legal — PHP forbids narrowing a parameter type in an
override. The cost is that an action missing `handle()` fails on first `execute()` instead
of at class-declaration time, so assert `hasMethod('handle')` over your actions in an
architecture test.

Only add `rollback()` when `handle()` causes a non-transactional side effect (an
external charge, a third-party API call) — the transaction already undoes database
writes. It takes no arguments; capture whatever it needs, params included, as an
instance property during `handle()`:

```php
protected function rollback(): void
{
    if ($this->chargeId !== null) {
        $this->gateway->refund($this->chargeId);
    }
}
```

### 5. Call it

```php
$post = (new PublishPost)->execute($params);       // do it, or throw whatever the refusing precondition threw
$canPublish = (new PublishPost)->permits($params);   // bool, for whether a control renders
```

Catch whatever exception a precondition throws wherever the app needs to react to a
refusal — this package renders nothing itself and ships no exception type; that's the
application's own exception, thrown directly from the precondition pipe. Calling
`execute()` from inside a caller-managed transaction — a plain `DB::transaction()`,
another action's `handle()`, `RefreshDatabase` in tests — is supported: the action's own
transaction becomes a savepoint inside the caller's.

## Rules, References, and Templates

Read before executing:

- no additional resource files for this skill; the generated stubs
  (`vendor/dashworthy/actions/stubs/*.stub`) are the canonical shape for every artifact

## Examples

- Adding a new write path (e.g. "archive a post"): generate the action, write its
  params DTO, write one precondition per business rule, implement `handle()`,
  call `execute()` from the controller/job/command instead of writing transaction and
  authorization logic inline.

## Anti-patterns

- Do not call `handle()` directly; it is `protected` for exactly this reason — always
  go through `execute()` or `permits()`.
- Do not type `handle()` against the `ActionParams` interface. Name the action's own
  `{Name}ActionParams` so a wrong argument is a `TypeError` at the boundary rather than
  an undefined-property error midway through the work.
- Do not declare an action `final`; tests need to subclass or mock it.
- Do not put a `DB::transaction()` call inside `handle()`; `execute()` already owns one.
- Do not reach for `rollback()` for anything the database transaction already undoes.
- Do not expect the package to defer events for you — it dispatches nothing. For a side
  effect that must wait for the commit, use `ShouldQueueAfterCommit` on the notification
  or job, or call `DB::afterCommit()` from inside `handle()`.
