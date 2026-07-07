<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

use Vusys\LaravelSmarty\Attributes\SmartyPlugin;

#[SmartyPlugin(type: 'modifier', name: 'dual_a')]
#[SmartyPlugin(type: 'modifier', name: 'dual_b', cacheable: false)]
class DualTaggedThing
{
    public function __invoke(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
