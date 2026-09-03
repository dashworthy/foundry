# Action Best Practices

An `Action` is a write expressed as one class: it takes exactly one `Data` object, runs its
preconditions, does the work inside a database transaction, and returns whatever the work
produced. Its read-side twin is the `Query`.

```php
use Dashworthy\Foundry\Actions\Action;

/**
 * @extends Action<PublishPostData, Post>
 */
class PublishPost extends Action
{
    protected function preconditions(): array
    {
        return [new ActorOwnsPost];
    }

    protected function handle(PublishPostData $data): Post
    {
        $data->post->update(['published_at' => now()]);

        return $data->post;
    }
}
```

## Type handle() against the unit's own Data

Type `handle()` against the action's `{Name}Data`, never the base `Data`, so a wrong
argument is a `TypeError` at the boundary rather than an undefined-property error midway
through the work. `handle()` is declared on the base as a `@method` annotation (not a real
abstract method) precisely so this narrowing is legal — PHP forbids narrowing a parameter
type in an override.

The cost: an action missing `handle()` fails on first `execute()`, not at
class-declaration time. Buy that back with an architecture test asserting every `Action`
subclass declares `handle()`.

## Keep the @extends return type honest

PHPStan reads the action's return type from the second `@extends Action<{Name}Data, …>`
argument, not from the `handle()` signature. When `handle()` returns a `Post`, the
annotation must say `Post`. The generated stub returns `null` with `@extends Action<…,
null>` — update both together.

## Do not make it final

Leave the class non-`final` so tests can subclass or mock it. `execute()` is `final` on the
base — that is what guarantees assert-then-transaction order can never be reordered — but
the action class itself must stay open.

## Calling it

```php
$post = PublishPost::run($data);                    // resolve from container + execute
$post = PublishPost::make()->execute($data);         // hold the instance
$can  = PublishPost::make()->permits($data);         // bool — for whether a control renders
```

- `make()` resolves from the container, so a bound decorator or fake is honoured. Prefer it
  to `new`, which skips the container.
- `run()` is `make()` + `execute()`.
- `execute()` asserts the preconditions, then runs `handle()` inside a `DB::transaction()`.
  Nesting is supported: called inside a caller-managed transaction (a `DB::transaction()`,
  another action's `handle()`, `RefreshDatabase` in tests), the inner transaction becomes a
  savepoint.

## withoutTransaction() — the deliberate opt-out

A transaction is the baseline for every action. Drop it for one call only when `handle()`
calls an external service mid-flight (holding a transaction open across a network round-trip
is itself the bug), or when a caught constraint violation must not abort a surrounding
transaction. The opt-out lives on the call, not the class:

```php
PublishPost::make()->withoutTransaction()->execute($data);
```

## withoutPreconditions() — avoid it

`withoutPreconditions()` turns the precondition gate off for one call, the same shape as
`withoutTransaction()`. It is not a convenience — skipping the gate runs the work for an
actor the rules would have refused, which in a request flow is a security bug. Reserve it
for a trusted caller that has already enforced the rules itself (a migration, a seeder, a
maintenance command). See `references/preconditions.md`.

## rollback() — only for non-transactional side effects

The transaction already undoes database writes. Override `rollback()` only to undo a side
effect the transaction cannot — an external charge, a third-party API call. It takes no
arguments; capture what it needs (params included) as an instance property during
`handle()`:

```php
protected function rollback(): void
{
    if ($this->chargeId !== null) {
        $this->gateway->refund($this->chargeId);
    }
}
```

`rollback()` runs after the transaction has rolled back and before the exception rethrows.
An exception thrown inside `rollback()` itself is reported, not raised, so the original
failure is the one the caller sees.

## Faking in tests

`fake()` swaps the action in the container for a partial mock. `preconditions()` is stubbed
to none by default (so the test need not satisfy authorization); `handle()` is not stubbed
— supply a return value of the right type. Needs `mockery/mockery` (a dev dependency). The
seam is `handle()` and `preconditions()`, never `execute()`.

```php
PublishPost::fake(fn (MockInterface $mock) => $mock
    ->shouldReceive('handle')->once()->andReturn($post));
```

## Anti-patterns

- Do not call `handle()` directly; it is `protected`. Go through `execute()`, `run()`, or
  `permits()`.
- Do not put a `DB::transaction()` inside `handle()`; `execute()` already owns one.
- Do not reach for `rollback()` for anything the database transaction already undoes.
- Do not declare the action `final`; tests need to subclass or mock it.
- Do not build it with `new` at a call site — use `make()`/`run()` so a bound decorator or
  fake is honoured.
- Do not expect the package to defer events — it dispatches nothing. For a side effect that
  must wait for the commit, use `ShouldQueueAfterCommit` or `DB::afterCommit()` inside
  `handle()`.
