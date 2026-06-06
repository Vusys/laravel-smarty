<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

/**
 * Fixture for nameFromConvention(): a static $name property must be
 * ignored — the override-detection contract is an *instance* property
 * with a literal default. If the static guard regresses, the descriptor
 * would report 'static_override' instead of the convention default.
 */
class StaticNamedModifier
{
    public static string $name = 'static_override';

    public function __invoke(mixed $value): string
    {
        return (string) $value;
    }
}
