# Data Best Practices

`Data` is the single argument every `Action`, `Query`, and `Precondition` takes: one
`final readonly` DTO carrying everything the unit needs, so a call hands over one object
instead of a fistful of loose values.

```php
use Dashworthy\Foundry\Data\Data;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class PublishPostData extends Data
{
    public function __construct(
        public Authenticatable $user,
        public Post $post,
    ) {}
}
```

## One DTO per unit

Every action and query names its own `{Name}Data`. `make:action` and `make:query`
scaffold it alongside the class and wire it into the `@extends` annotation; use `make:data`
on its own only when a `Data` class must exist without a matching unit — for example one
shared by several actions.

## It is a plain DTO — nothing more

- No interface to implement, no actor/target contracts. A precondition that needs to know
  who is acting reads it from a property you put on the `Data`.
- Declare `Data` as the constructor's promoted, `public readonly` properties. Anything a
  precondition or `handle()` reads should be reachable from the object.
- `Data` itself is `abstract readonly`: a `final readonly` subclass is only legal when its
  parent is readonly too, so the base has to be readonly, and it must be `abstract` because
  it is never instantiated directly. It declares no state and enforces nothing — it exists
  purely to be the one shared parameter type the whole toolkit binds to.

## Normalise at construction

Because it is `readonly`, a `Data` is the right place to canonicalise a value once, so
every consumer keys off one form:

```php
final readonly class PendingInvitationsData extends Data
{
    public string $email;

    public function __construct(string $email)
    {
        $this->email = strtolower($email);   // case-insensitive lookups key off one form
    }
}
```

## Anti-patterns

- Do not add business logic or query/persistence behavior to a `Data` class — it carries
  values; the `Action` or `Query` does the work.
- Do not make it mutable (drop `readonly`) or add setters — the immutability is what lets
  `rollback()` and preconditions trust the values they were handed.
- Do not reuse one `Data` class across unrelated units just to avoid writing a class; a
  wrong-shaped `Data` should be a `TypeError` at the boundary.
- Do not type a `handle()` or precondition against the base `Data` when a concrete
  `{Name}Data` exists — name the subclass so a wrong argument fails at the boundary.
