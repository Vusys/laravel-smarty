<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins\Subdir;

class NestedModifier
{
    public function __invoke(mixed $value): string
    {
        return 'nest:'.$value;
    }
}
