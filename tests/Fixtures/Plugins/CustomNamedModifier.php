<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

class CustomNamedModifier
{
    public string $name = 'shouty';

    public function __invoke(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
