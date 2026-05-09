<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

use Vusys\LaravelSmarty\Attributes\SmartyPlugin;

#[SmartyPlugin(type: 'modifier', name: 'shrunk')]
class AttributeTaggedThing
{
    public function __invoke(mixed $value): string
    {
        return strtolower((string) $value);
    }
}
