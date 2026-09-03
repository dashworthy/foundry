# Query Best Practices

A `Query` is a read expressed as one class: it takes exactly one `Data` object, evaluates
its preconditions, runs the read, and returns the rows as a `Collection` of models. It
mirrors the write side, the `Action` — same `make()`/`run()`/`fake()` helpers, same
precondition pipeline — minus the transaction, because a read changes nothing to roll back.

```php
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Query<PendingInvitationsData, TeamInvitation>
 */
class PendingInvitations extends Query
{
    /**
     * @return Builder<TeamInvitation>
     */
    protected function handle(PendingInvitationsData $data): Builder
    {
        return TeamInvitation::query()
            ->whereRaw('LOWER(email) = ?', [$data->email])
            ->whereNull('accepted_at')
            ->latest();
    }
}
```

## handle() builds the read — it does not run it

`handle()` only *builds* the read and hands back the Eloquent builder. The base asserts the
preconditions, runs `->get()`, and returns the rows. A concrete query never calls `->get()`
(or `->first()`, `->paginate()`, …). That keeps "how a read is executed" in one place, so a
subclass cannot forget to run it, run it twice, or run it before the preconditions pass.

## A query does not shape its results

It returns the models it read. Turning those into whatever a page, an export, or an API
needs is the caller's job. Keep the query about *which rows*; keep shaping out of it.

## Type the model, keep the @extends honest

The second `@extends Query<{Name}Data, TModel>` argument is the model the returned
`Collection` holds — set it to the real model (and match the `@return Builder<TModel>` on
`handle()`) so callers keep the type without a cast. Type `handle()` against the query's own
`{Name}Data`, never the base `Data`; `handle()` is a `@method` annotation on the base so
this narrowing is legal.

The cost: a query missing `handle()` fails on first `get()`, not at class-declaration time.
Buy that back with an architecture test asserting every `Query` subclass declares
`handle()`.

## Calling it

```php
$rows = PendingInvitations::run($data);               // Collection<int, TeamInvitation>
$rows = PendingInvitations::make()->get($data);
$can  = PendingInvitations::make()->permits($data);   // bool — same precondition gate as Action
```

- `make()` resolves from the container (honours a bound decorator or fake); prefer it to
  `new`.
- `run()` is `make()` + `get()`.
- `get()` is `final` on the base — assert-then-read order cannot be reordered by a subclass.

## Faking in tests

`fake()` swaps the query in the container for a partial mock. `preconditions()` is stubbed
to none by default; `handle()` is not stubbed — supply a builder of the right type. Needs
`mockery/mockery`. The seam is `handle()` and `preconditions()`, never `get()`.

## Anti-patterns

- Do not call `->get()`, `->first()`, `->paginate()`, or `->count()` inside `handle()`;
  return the builder and let the base run it.
- Do not shape rows (map to arrays, build DTOs, paginate for a view) inside the query — that
  is the caller's job.
- Do not add a `DB::transaction()` to a query; a read has nothing to roll back.
- Do not type `handle()` against the base `Data` when a concrete `{Name}Data` exists.
- Do not build it with `new` at a call site — use `make()`/`run()`.
