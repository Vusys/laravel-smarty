<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

/**
 * Fixture for nameFromConvention(): a non-public $name property must be
 * ignored by the override detector, and the convention-derived name
 * returned. If the public-only guard regresses to allow private
 * properties, the descriptor would report 'private_override' instead.
 */
class PrivateNamedModifier
{
    private string $name = 'private_override';

    public function __invoke(mixed $value): string
    {
        return (string) $value;
    }
}
