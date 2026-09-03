<?php

use Illuminate\Support\Facades\Process;

it('finds no errors in a generated-shape action/params pair (regression for the stub namespace bug)', function () {
    // Invoke the phar through the current PHP binary rather than the
    // `vendor/bin/phpstan` console wrapper: that wrapper is a Unix shebang
    // script, and Symfony Process cannot execute it on Windows, so the run
    // returned a non-zero exit before analysis even began. Running the phar with
    // PHP_BINARY is identical on every platform.
    $result = Process::path(dirname(__DIR__, 2))
        ->timeout(120)
        ->run([
            PHP_BINARY,
            'vendor/phpstan/phpstan/phpstan.phar',
            'analyse',
            '--configuration=tests/PHPStan/phpstan-clean-fixtures.neon',
            '--no-progress',
            '--error-format=json',
        ]);

    // Surface phpstan's own output on failure — a bare "1 is not 0" says nothing
    // about which rule fired or why the process could not start.
    $diagnostic = "phpstan stdout:\n{$result->output()}\n\nphpstan stderr:\n{$result->errorOutput()}";

    expect($result->exitCode())->toBe(0, $diagnostic);

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

    expect($fileErrors)->toBe(0, $diagnostic);
});
