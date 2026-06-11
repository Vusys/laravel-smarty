<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\MultiDir;

/**
 * Lives in Fixtures/MultiDirA — one of two PSR-4 roots mapped to the
 * MultiDir namespace (the mapping is registered at runtime by
 * PluginScannerTest). Exists to prove the scanner walks *every*
 * directory a namespace resolves to, not just the first.
 */
final class AlphaModifier
{
    public function __invoke(mixed $value): string
    {
        return 'alpha:'.(is_scalar($value) ? (string) $value : '');
    }
}
