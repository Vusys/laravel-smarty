<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;

// This package supports Laravel 10–13 simultaneously, so we deliberately do
// NOT pull in the per-version Laravel level sets (LaravelLevelSetList /
// LaravelSetProvider) — those rewrite code to a target framework version and
// would break support for older ones. Stick to version-agnostic quality
// sets, plus a PHP set pinned at the minimum supported runtime.
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php81: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
    ])
    // The PluginScanner name-detection fixtures exist specifically to
    // assert that a `private $name` (or static / empty) is ignored — the
    // property has to stay on the class for reflection to see it, even
    // though no PHP code reads it.
    ->withSkip([
        RemoveUnusedPrivatePropertyRector::class => [
            __DIR__.'/tests/Fixtures/Plugins/PrivateNamedModifier.php',
        ],
    ]);
