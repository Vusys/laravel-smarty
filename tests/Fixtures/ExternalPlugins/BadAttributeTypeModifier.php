<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins;

use Vusys\LaravelSmarty\Attributes\SmartyPlugin;

#[SmartyPlugin(type: 'wrong-type', name: 'x')]
class BadAttributeTypeModifier
{
    public function __invoke(mixed $value): mixed
    {
        return $value;
    }
}
