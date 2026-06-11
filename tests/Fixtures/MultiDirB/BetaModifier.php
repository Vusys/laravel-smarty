<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\MultiDir;

/**
 * Second half of the two-directory MultiDir namespace — see
 * AlphaModifier in Fixtures/MultiDirA.
 */
final class BetaModifier
{
    public function __invoke(mixed $value): string
    {
        return 'beta:'.(is_scalar($value) ? (string) $value : '');
    }
}
