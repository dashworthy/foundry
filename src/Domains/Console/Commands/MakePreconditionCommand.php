<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains\Console\Commands;

use Dashworthy\Foundry\Console\Commands\PreconditionMakeCommand;
use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;

class MakePreconditionCommand extends PreconditionMakeCommand
{
    use InteractsWithDomains;
}
