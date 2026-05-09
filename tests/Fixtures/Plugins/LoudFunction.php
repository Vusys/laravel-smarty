<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

class LoudFunction
{
    public function __invoke(array $params): string
    {
        $text = isset($params['text']) && is_string($params['text']) ? $params['text'] : '';

        return strtoupper($text).'!';
    }
}
