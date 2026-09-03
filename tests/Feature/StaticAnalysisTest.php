<?php

use Illuminate\Support\Facades\Process;

it('finds no errors in a generated-shape action/params pair (regression for the stub namespace bug)', function () {
    $result = Process::path(dirname(__DIR__, 2))
        ->timeout(120)
        ->run('vendor/bin/phpstan analyse --configuration=tests/PHPStan/phpstan-clean-fixtures.neon --no-progress --error-format=json');

    expect($result->exitCode())->toBe(0);

    /**
     * PHPStan's documented `--error-format=json` schema nests the count under
     * `totals.file_errors`. Some sandboxed tool-output wrappers substitute a
     * condensed shape (`errors` at the top level) regardless of the requested
     * format, so both are accepted here — either way, a real static-analysis
     * failure must be reported, not just a non-zero exit code.
     *
     * @var array{totals?: array{file_errors?: int}, errors?: int} $report
     */
    $report = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    $fileErrors = $report['totals']['file_errors'] ?? $report['errors'] ?? 0;

    expect($fileErrors)->toBe(0);
});
