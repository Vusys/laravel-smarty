<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

/**
 * Fixture for nameFromConvention(): an empty-string default on $name
 * must not be treated as an override, since registering a plugin with
 * the name '' would be unusable. The convention-derived name should
 * win instead.
 */
class EmptyNameModifier
{
    public string $name = '';

    public function __invoke(mixed $value): string
    {
        return (string) $value;
    }
}
