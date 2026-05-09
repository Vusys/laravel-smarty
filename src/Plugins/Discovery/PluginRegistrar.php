<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins\Discovery;

use Smarty\Smarty;
use Vusys\LaravelSmarty\Exceptions\PluginRegistrationException;

/**
 * Installs a list of discovered plugin descriptors onto a Smarty
 * instance. The plugin object itself is resolved through the container
 * lazily (on first invocation per render), which keeps the per-render
 * registration cost flat — unused plugins never get instantiated.
 *
 * Collisions inside the discovered set throw rather than silently
 * shadow: if two classes both end up registered as `(modifier, since)`,
 * we want a loud failure at registration so the misconfiguration is
 * obvious, not a mystery rendering bug down the line.
 */
class PluginRegistrar
{
    /**
     * @param  array<int, PluginDescriptor>  $descriptors
     */
    public static function register(Smarty $smarty, array $descriptors): void
    {
        /** @var array<string, class-string> $registered */
        $registered = [];

        foreach ($descriptors as $descriptor) {
            $key = $descriptor->type.':'.$descriptor->name;

            if (isset($registered[$key])) {
                throw PluginRegistrationException::duplicateName(
                    $descriptor->type,
                    $descriptor->name,
                    $registered[$key],
                    $descriptor->class,
                );
            }

            $smarty->registerPlugin(
                self::smartyPluginType($descriptor->type),
                $descriptor->name,
                self::buildCallable($descriptor->type, $descriptor->class),
            );

            $registered[$key] = $descriptor->class;
        }
    }

    /**
     * @param  'modifier'|'function'|'block'  $type
     */
    private static function smartyPluginType(string $type): string
    {
        return match ($type) {
            'modifier' => Smarty::PLUGIN_MODIFIER,
            'function' => Smarty::PLUGIN_FUNCTION,
            'block' => Smarty::PLUGIN_BLOCK,
        };
    }

    /**
     * @param  'modifier'|'function'|'block'  $type
     * @param  class-string  $class
     */
    private static function buildCallable(string $type, string $class): callable
    {
        return match ($type) {
            'modifier' => static fn (mixed ...$args): mixed => app()->make($class)(...$args),
            'function' => static fn (array $params): string => (string) app()->make($class)($params),
            // For blocks the by-reference `$repeat` parameter must propagate
            // back through the closure into Smarty so the body short-circuit
            // path works. PHP binds the reference as long as the user's
            // __invoke also declares `&$repeat`.
            'block' => static fn ($params, $content, $template, &$repeat): string => (string) app()->make($class)($params, $content, $template, $repeat),
        };
    }
}
