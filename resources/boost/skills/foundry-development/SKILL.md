---
name: foundry-development
description: >
  Define and run dashworthy/foundry Data, Preconditions, Actions, and Queries in a
  Laravel application: one readonly Data DTO per unit, preconditions shared across the
  write and read sides, Actions that own a database transaction and roll back on failure,
  and Queries that build a read the base runs.
license: MIT
metadata:
  author: Andrew Leach
---

# Foundry

Use this skill whenever a Laravel application defines or calls a `dashworthy/foundry`
`Action` or `Query` — creating one, writing its `Data` DTO, adding a `Precondition`,
wiring `execute()`/`get()`/`permits()` at a call site, or handling a refusal.

## Primary Goal

Model a write as an `Action` and a read as a `Query`, each taking exactly one `Data`
object, each gated by the same reusable `Precondition` pipes. The base classes own the
plumbing: an `Action` runs `handle()` inside a database transaction and calls `rollback()`
on failure; a `Query` runs the builder `handle()` returns and hands back a `Collection`.

## The four pieces

| Piece | Namespace | Shape |
| --- | --- | --- |
| `Data` | `Dashworthy\Foundry\Data` | `abstract readonly class Data` — the one argument every unit takes |
| `Precondition` | `Dashworthy\Foundry\Preconditions` | `interface` with `handle(Data $data, Closure $next): mixed` |
| `Action` | `Dashworthy\Foundry\Actions` | `abstract class Action` — write side, owns the transaction |
| `Query` | `Dashworthy\Foundry\Queries` | `abstract class Query` — read side, no transaction |

## Generate the pieces

```bash
php artisan make:action PublishPost          # also scaffolds PublishPostData
php artisan make:query PendingInvitations     # also scaffolds PendingInvitationsData
php artisan make:data SomeSharedData          # a Data DTO on its own
php artisan make:precondition ActorOwnsPost
```

`make:action` and `make:query` scaffold the matching `{Name}Data` and wire it into the
`@extends` annotation. Use `make:data` alone only when a `Data` class must exist without a
matching unit. Pass `--domain` / `--subdomain` to place files under `app/Domains/…` — see
the `domain-generators` skill. The generated stubs
(`vendor/dashworthy/foundry/stubs/*.stub`) are the canonical shape for every artifact.

## Reference Index

Read the doc for each piece you are creating or changing before editing.

| Working on | Read |
| --- | --- |
| The `{Name}Data` DTO — properties, normalising, immutability | [`references/data.md`](references/data.md) |
| A `Precondition` — the pipe, ordering, sharing across sides, refusals | [`references/preconditions.md`](references/preconditions.md) |
| An `Action` — `handle()`, transactions, `rollback()`, `withoutTransaction()`, faking | [`references/actions.md`](references/actions.md) |
| A `Query` — building vs running the read, typing the model, faking | [`references/queries.md`](references/queries.md) |

## The shape end to end

```
make:action PublishPost
        │
        ▼
PublishPostData (Data)  ──▶  PublishPost (Action)
   one readonly DTO            preconditions() → ActorOwnsPost (Precondition)
                               handle() inside a transaction
                               │
        PublishPost::run($data) ──▶ returns what handle() produced
```

A `Query` is the same picture without the transaction: `handle()` returns a builder, the
base runs it, `PendingInvitations::run($data)` returns a `Collection`.

## Examples

- A new write path ("archive a post"): `make:action ArchivePost`, fill in `ArchivePostData`,
  write one precondition per business rule, implement `handle()`, call
  `ArchivePost::run($data)` from the controller/job/command instead of writing transaction
  and authorization logic inline. See `references/actions.md`.
- A new read ("a member's pending invitations"): `make:query PendingInvitations`, build the
  builder in `handle()`, return `PendingInvitations::run($data)` and shape the rows in the
  caller. See `references/queries.md`.

## Anti-patterns

Each reference doc carries the full list for its piece. The ones that span all four:

- Do not call `handle()` directly; it is `protected`. Go through `execute()`/`get()`,
  `run()`, or `permits()`.
- Do not type `handle()` (or a precondition) against the base `Data` when a concrete
  `{Name}Data` exists — name the subclass so a wrong argument fails at the boundary.
- Do not build a unit with `new` at a call site — use `make()`/`run()` so a bound decorator
  or fake is honoured.
- Do not declare an `Action` or `Query` `final`; tests need to subclass or mock it.
