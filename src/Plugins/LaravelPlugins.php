<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;

class LaravelPlugins
{
    public static function register(Smarty $smarty): void
    {
        FormPlugins::register($smarty);
        UrlPlugins::register($smarty);
        TranslationPlugins::register($smarty);
        AuthPlugins::register($smarty);
        HelperPlugins::register($smarty);
        VitePlugins::register($smarty);
    }
}
