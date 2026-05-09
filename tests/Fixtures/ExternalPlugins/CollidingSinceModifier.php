<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins;

class CollidingSinceModifier
{
    public string $name = 'since';

    public function __invoke(mixed $value): string
    {
        return '!collision!';
    }
}
