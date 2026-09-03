# Precondition Best Practices

A `Precondition` is one rule checked before an `Action` or a `Query` runs. It is shared
across both sides, so the same rule can gate a write and the read of the same entity from
one class.

```php
use Closure;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\Preconditions\Precondition;

final class ActorOwnsPost implements Precondition
{
    public function handle(Data $data, Closure $next): mixed
    {
        if (! $data instanceof PublishPostData) {
            throw new InvalidArgumentException('ActorOwnsPost expects PublishPostData.');
        }

        if ($data->post->user_id !== $data->user->getAuthIdentifier()) {
            throw new NotPostOwner('You do not own this post.');
        }

        return $next($data);
    }
}
```

## It is a Pipeline pipe

A precondition runs as a pipe in a Laravel `Illuminate\Pipeline\Pipeline` — the same
chain-of-responsibility convention as HTTP middleware. `handle()` receives the `Data` and
the next pipe: call `$next($data)` to let the chain continue, or throw to refuse.

## One rule per class, cheapest first

- Keep each precondition to a single rule; compose several by listing them in
  `preconditions()`.
- Evaluation stops at the first pipe that throws, so ordering is a correctness property,
  not a style choice: put authorization ahead of state checks, so an unauthorized actor
  never learns the state. Put the cheapest checks first.

## Type on the base Data, narrow inside

Because a precondition is shared between the write and the read, it types on the base
`Data`, not one subclass. Narrow when it needs a specific shape — with an `instanceof`
(as above), or against a contract your application layers onto its own `Data` classes:

```php
public function handle(Data $data, Closure $next): mixed
{
    if ($data instanceof HasOwner && $data->owner()->isNot($data->actor())) {
        throw new NotOwner;
    }

    return $next($data);
}
```

## The refusal exception is the application's

The package ships no exception type and never wraps what a precondition throws — whatever
it throws propagates unchanged out of `execute()` or `get()`. Define and throw the
application's own exception, and catch it wherever the app reacts to a refusal.

## Registering preconditions

An action or query returns its preconditions in evaluation order from `preconditions()`.
Return either instantiated pipe objects or class-strings — the Pipeline resolves
class-strings from the container:

```php
protected function preconditions(): array
{
    return [ActorIsAuthenticated::class, new ActorOwnsPost];
}
```

## Bypassing the gate — `withoutPreconditions()`, avoid it

Both `Action` and `Query` expose `withoutPreconditions()`, which turns the gate off for one
call (`SomeAction::make()->withoutPreconditions()->execute($data)`) — the mirror of
`withoutTransaction()`. **Treat it as a red flag, not a convenience.** Preconditions are
where a unit's authorization and state rules live, so skipping them runs the work for an
actor the rules would have refused; in a request flow that is a security bug. Once the gate
is off, `permits()` also reports `true` unconditionally, because there is no rule left to
refuse.

It exists only for a trusted caller that has already enforced the rules itself — a data
migration, a seeder, a maintenance command running as no one. If you reach for it in a
controller, a job, or anything serving a user, the design is wrong: fix the precondition or
the data instead.

## Anti-patterns

- Do not do work in a precondition — it is a check. No writes, no side effects, no
  dispatching. It either lets the chain continue or throws.
- Do not narrow the `handle()` signature to a `{Name}Data` subclass — it must stay
  `handle(Data $data, ...)` so the shared pipe type-checks against every unit. Narrow with
  `instanceof` in the body instead.
- Do not bundle several rules into one precondition; a chain of small pipes is what makes
  ordering and reuse work.
- Do not swallow a refusal inside the pipe by returning `$next($data)` anyway — throw so
  the chain actually stops.
