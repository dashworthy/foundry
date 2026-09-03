<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains\Console\Commands;

use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;
use Illuminate\Foundation\Console\EnumMakeCommand as BaseEnumMakeCommand;

class EnumMakeCommand extends BaseEnumMakeCommand
{
    use InteractsWithDomains;
}
