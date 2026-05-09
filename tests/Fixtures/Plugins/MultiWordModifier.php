<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

class MultiWordModifier
{
    public function __invoke(mixed $value): string
    {
        return 'mw('.$value.')';
    }
}
