<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\EmptyNamePlugin;

/**
 * Fixture for nameFromConvention(): a class named exactly after its
 * type suffix strips down to an empty derived name. The scanner must
 * reject it loudly rather than register a nameless tag.
 *
 * Kept in its own namespace (not Tests\Fixtures\Plugins) so the
 * wholesale-namespace discovery tests don't pick it up and throw.
 */
class Modifier
{
    public function __invoke(mixed $value): string
    {
        return (string) $value;
    }
}
