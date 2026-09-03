<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A minimal Eloquent model backing the query fixtures. The `widgets` table is
 * created per test (see the suite's beforeEach), so the base class's `->get()`
 * has real rows to materialise into a `Collection` of models.
 *
 * @property string $name
 */
class Widget extends Model
{
    /** @var list<string> */
    protected $fillable = ['name'];

    public $timestamps = false;
}
