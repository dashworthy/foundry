<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains\Console\Commands;

use Dashworthy\Foundry\Console\Commands\DataMakeCommand;
use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;

class MakeDataCommand extends DataMakeCommand
{
    use InteractsWithDomains;
}
