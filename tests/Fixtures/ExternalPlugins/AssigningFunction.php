<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins;

use Smarty\Template;

/**
 * Function plugin that writes a template variable — exercises the
 * $template argument PluginRegistrar forwards to discovered function
 * plugins (the assign= idiom every comparable built-in supports).
 */
final class AssigningFunction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __invoke(array $params, Template $template): string
    {
        $name = $params['assign'] ?? 'assigned';
        $template->assign(is_string($name) ? $name : 'assigned', 'from-plugin');

        return '';
    }
}
