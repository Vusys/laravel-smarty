<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Arr;
use Smarty\Smarty;

class HtmlPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(
            Smarty::PLUGIN_FUNCTION,
            'class',
            static fn (array $params): string => Arr::toCssClasses(self::extractArray($params))
        );

        $smarty->registerPlugin(
            Smarty::PLUGIN_FUNCTION,
            'style',
            static fn (array $params): string => Arr::toCssStyles(self::extractArray($params))
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int|string, bool|int|string>
     */
    private static function extractArray(array $params): array
    {
        $array = $params['array'] ?? [];

        if (! is_array($array)) {
            return [];
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_bool($value) || is_int($value) || is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
