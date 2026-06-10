<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins;

use Vusys\LaravelSmarty\Attributes\SmartyPlugin;

/**
 * Counter plugin marked cacheable=false via the attribute — used to
 * prove the flag survives descriptor → cache payload → registrar and
 * that the tag re-evaluates on output-cache hits.
 */
#[SmartyPlugin(type: 'function', name: 'tick', cacheable: false)]
final class TickFunction
{
    public static int $count = 0;

    public function __invoke(): string
    {
        return (string) ++self::$count;
    }
}
