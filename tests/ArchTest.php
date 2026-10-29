<?php

declare(strict_types=1);

use Dashworthy\Foundry\Actions\Action;
use Dashworthy\Foundry\Data\Data;
use Dashworthy\Foundry\FoundryServiceProvider;
use Dashworthy\Foundry\Preconditions\Precondition;
use Dashworthy\Foundry\Queries\Query;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

/*
 | InteractsWithDomains is the one file that must NOT declare strict types: its
 | handle() is typed `?bool` to stay return-type compatible with the generator
 | commands it mixes into, but it returns the int SUCCESS/FAILURE constants and
 | relies on PHP's implicit int↔bool coercion (0↔false, 1↔true) to round-trip
 | them through the console kernel. strict_types would fatal that.
 */
arch('the package source declares strict types')
    ->expect('Dashworthy\Foundry')
    ->toUseStrictTypes()
    ->ignoring('Dashworthy\Foundry\Domains\Concerns\InteractsWithDomains');

/*
 | Action and Query both declare handle() as a @method annotation rather than a
 | real abstract method, so each subclass can typehint its own {Name}Data instead
 | of widening to the base Data. The cost is that PHP no longer fatals at
 | class-declaration time on a subclass that never declares handle() — it fails
 | on first execute()/get(). These rules buy that back, enforced across the
 | package's own Action and Query subclasses (its test fixtures).
 */
it('has every action and query declaring the handle() the base class calls', function (string $base) {
    $subclasses = collect(File::allFiles(__DIR__.'/Fixtures'))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->map(fn (SplFileInfo $file): string => 'Dashworthy\\Foundry\\Tests\\Fixtures\\'.str($file->getRelativePathname())
            ->beforeLast('.php')
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->toString())
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, $base))
        ->reject(fn (string $class): bool => (new ReflectionClass($class))->isAbstract());

    expect($subclasses)->not->toBeEmpty();

    $missing = $subclasses
        ->reject(fn (string $class): bool => (new ReflectionClass($class))->hasMethod('handle'))
        ->values()
        ->all();

    expect($missing)->toBe([]);
})->with([
    'actions' => [Action::class],
    'queries' => [Query::class],
]);

arch('every source class keeps to an approved directory')
    ->expect('Dashworthy\Foundry')
    ->toUseApprovedDirectories(
        [
            'Actions' => Action::class,
            'Concerns',
            'Console' => Command::class,
            'Data' => Data::class,
            'Domains',
            'Preconditions' => Precondition::class,
            'Queries' => Query::class,
        ],
        beneath: 'Dashworthy\Foundry',
    )
    ->ignoring(FoundryServiceProvider::class);

arch('every class is named for the directory it sits in')
    ->expect('Dashworthy\Foundry')
    ->toUseDirectorySuffixes(
        [
            'Console' => 'Command',
            'Exceptions' => 'Exception',
        ],
        beneath: 'Dashworthy\Foundry',
    );

arch('every source class has a mirrored test')
    ->expect('Dashworthy\Foundry')
    ->toHaveTests([
        dirname(__DIR__).'/src' => [__DIR__.'/Unit', __DIR__.'/Feature'],
    ]);
