<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Facades\URL;
use Smarty\Smarty;

class UrlPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'route', static function (array $params): string {
            $name = $params['name'] ?? '';
            unset($params['name']);

            return route($name, $params);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'url', static fn (array $params): string => url($params['path'] ?? ''));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'asset', static fn (array $params): string => asset($params['path'] ?? ''));

        // Signed URLs are non-cacheable: every render reads the app key
        // and embeds a fresh signature. Baking a signature into the
        // output cache would either pin a stale URL or, for the
        // temporary variant, ship an already-expired link.
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'signed_route', static function (array $params): string {
            $name = $params['name'] ?? '';
            unset($params['name']);

            return URL::signedRoute($name, $params);
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'temporary_signed_route', static function (array $params): string {
            $name = $params['name'] ?? '';
            $expiration = $params['expiration'] ?? null;
            unset($params['name'], $params['expiration']);

            return URL::temporarySignedRoute($name, $expiration, $params);
        }, false);
    }
}
