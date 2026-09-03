<?php

namespace Dashworthy\Foundry\Tests\Fixtures;

use Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains;
use Illuminate\Console\GeneratorCommand;

class DomainAwareFixtureCommand extends GeneratorCommand
{
    use InteractsWithDomains;

    protected $name = 'make:fixture-thing';

    protected $description = 'Fixture command for trait tests';

    protected $type = 'FixtureThing';

    protected function getStub(): string
    {
        return __DIR__.'/fixture.stub';
    }
}
