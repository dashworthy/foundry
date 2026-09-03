<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Data;

/**
 * The single argument every Action, Query, and Precondition takes — one
 * `final readonly` DTO carrying everything the unit needs, so a call hands over
 * one object instead of a fistful of loose values.
 *
 * A base class rather than a marker interface so the whole toolkit binds to one
 * concrete type: `Action::execute(Data $data)`, `Query::get(Data $data)`, and
 * `Precondition::handle(Data $data, ...)` all name it, and the `TData` template
 * on Action and Query is bounded by it. It is `abstract readonly` because a
 * concrete `final readonly class {Name}Data extends Data` is only legal when
 * the parent is itself readonly — PHP lets a readonly class extend only another
 * readonly class.
 *
 * It declares no state and enforces nothing at runtime; it exists to be that
 * shared parameter type. PHP forbids narrowing a parameter type in an override,
 * so each unit's `handle()` names its own `{Name}Data` subclass while the base
 * signatures name `Data`.
 */
abstract readonly class Data {}
