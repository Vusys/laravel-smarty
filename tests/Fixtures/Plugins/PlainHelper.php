<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

/**
 * No suffix, no #[SmartyPlugin] attribute — should be ignored by the
 * scanner. Lets a directory full of plugins coexist with utility
 * classes without polluting the registered set.
 */
class PlainHelper
{
    public function help(): string
    {
        return 'I am not a plugin.';
    }
}
