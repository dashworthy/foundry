<?php

declare(strict_types=1);

namespace Dashworthy\Foundry\Domains\Console\Commands;

use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;
use Illuminate\Foundation\Console\RequestMakeCommand as BaseRequestMakeCommand;

class RequestMakeCommand extends BaseRequestMakeCommand
{
    use InteractsWithDomains;
}
