<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

class WrapBlock
{
    public function __invoke(array $params, ?string $content, mixed $template, bool &$repeat): string
    {
        if ($content === null) {
            return '';
        }

        $tag = isset($params['tag']) && is_string($params['tag']) ? $params['tag'] : 'div';

        return '<'.$tag.'>'.$content.'</'.$tag.'>';
    }
}
