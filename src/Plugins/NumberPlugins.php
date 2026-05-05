<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Number;
use Smarty\Smarty;

class NumberPlugins
{
    public static function register(Smarty $smarty): void
    {
        // Illuminate\Support\Number ships with Laravel 11+. Stay quiet on
        // Laravel 10 rather than fatalling — these modifiers simply don't
        // register. Users on L10 keep Smarty's native number_format.
        if (! class_exists(Number::class)) {
            return;
        }

        $smarty->registerPlugin(
            Smarty::PLUGIN_MODIFIER,
            'currency',
            static fn (int|float $value, string $in = '', ?string $locale = null, ?int $precision = null): string => (string) Number::currency($value, $in, $locale, $precision)
        );

        $smarty->registerPlugin(
            Smarty::PLUGIN_MODIFIER,
            'file_size',
            static fn (int|float $bytes, int $precision = 0, ?int $maxPrecision = null): string => (string) Number::fileSize($bytes, $precision, $maxPrecision)
        );

        $smarty->registerPlugin(
            Smarty::PLUGIN_MODIFIER,
            'percentage',
            static fn (int|float $value, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string => (string) Number::percentage($value, $precision, $maxPrecision, $locale)
        );

        $smarty->registerPlugin(
            Smarty::PLUGIN_MODIFIER,
            'abbreviate',
            static fn (int|float $value, int $precision = 0, ?int $maxPrecision = null): string => (string) Number::abbreviate($value, $precision, $maxPrecision)
        );

        $smarty->registerPlugin(
            Smarty::PLUGIN_MODIFIER,
            'number_for_humans',
            static fn (int|float $value, int $precision = 0, ?int $maxPrecision = null, bool $abbreviate = false): string => (string) Number::forHumans($value, $precision, $maxPrecision, $abbreviate)
        );
    }
}
