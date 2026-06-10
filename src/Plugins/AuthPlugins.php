<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Facades\Gate;
use Smarty\Smarty;
use Smarty\Template;

class AuthPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'auth', static function ($params, $content, Template $template, &$repeat): string {
            if ($content === null) {
                $user = auth()->guard($params['guard'] ?? null)->user();

                if ($user === null) {
                    $repeat = false;

                    return '';
                }

                BlockState::push('auth.user', $template->getTemplateVars('user'));
                $template->assign('user', $user);

                return '';
            }

            if (BlockState::hasEntries('auth.user')) {
                $template->assign('user', BlockState::pop('auth.user'));
            }

            return (string) $content;
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'guest', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                if (! auth()->guard($params['guard'] ?? null)->guest()) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'can', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = self::gateArguments($params);

                if (! Gate::check($params['ability'] ?? '', $arguments)) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'cannot', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = self::gateArguments($params);

                if (! Gate::denies($params['ability'] ?? '', $arguments)) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'canany', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = self::gateArguments($params);
                $abilities = (array) ($params['abilities'] ?? []);
                $inverse = (bool) ($params['inverse'] ?? false);

                // Empty abilities is a programming mistake and fails
                // closed in both arms — see the same guard on {canall}.
                if ($abilities === []) {
                    $repeat = false;

                    return '';
                }

                if (Gate::any($abilities, $arguments) === $inverse) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'canall', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = self::gateArguments($params);
                $abilities = (array) ($params['abilities'] ?? []);
                $inverse = (bool) ($params['inverse'] ?? false);

                // Empty abilities is treated as a programming mistake and
                // fails closed in both arms — passing [] by accident
                // shouldn't suddenly render the inverse body to everyone.
                if ($abilities === []) {
                    $repeat = false;

                    return '';
                }

                if (Gate::check($abilities, $arguments) === $inverse) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);
    }

    /**
     * Gate arguments for {can}/{cannot}/{canany}/{canall}: `args=[...]`
     * is Blade's `@can('update', [$post, $extra])` multi-argument form
     * (policy methods with extra parameters); `model=` stays as the
     * common single-model shorthand. `args=` wins when both are given.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, mixed>
     */
    private static function gateArguments(array $params): array
    {
        if (array_key_exists('args', $params)) {
            $args = $params['args'];

            return is_array($args) ? array_values($args) : [$args];
        }

        return array_key_exists('model', $params) ? [$params['model']] : [];
    }
}
