<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains\Console\Commands;

use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;
use Illuminate\Foundation\Console\CastMakeCommand as BaseCastMakeCommand;

class CastMakeCommand extends BaseCastMakeCommand
{
    use InteractsWithDomains;
}
