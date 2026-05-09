<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

class SinceModifier
{
    public function __invoke(mixed $value): string
    {
        return $value === null ? '' : '['.$value.']';
    }
}
