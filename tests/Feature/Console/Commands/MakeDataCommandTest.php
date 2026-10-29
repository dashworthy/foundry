<?php

use Illuminate\Support\Facades\File;

/*
 | Every generator is domain-aware: given --domain/--subdomain, each places its
 | class (and any nested Data) under App\Domains\{Domain}\{Sub}\{folder}.
 */

it('creates a data object on its own in a domain', function () {
    $this->artisan('make:data', ['name' => 'ArchiveReportData', '--domain' => 'Ops', '--subdomain' => 'Reports'])
        ->assertExitCode(0);

    $path = $this->generatedPath('Domains/Ops/Reports/Data/ArchiveReportData.php');
    expect(File::exists($path))->toBeTrue();
    expect(File::get($path))->toContain('final readonly class ArchiveReportData extends Data');
});
