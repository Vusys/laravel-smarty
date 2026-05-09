<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins;

class StandaloneFunction
{
    public function __invoke(array $params): string
    {
        return 'standalone:'.($params['note'] ?? '');
    }
}
