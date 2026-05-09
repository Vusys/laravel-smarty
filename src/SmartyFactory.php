<?php

namespace Vusys\LaravelSmarty;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Smarty\Security;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Plugins\LaravelPlugins;
use Vusys\LaravelSmarty\Security\BalancedSecurityPolicy;
use Vusys\LaravelSmarty\Security\StrictSecurityPolicy;

/**
 * @phpstan-type SmartyConfig array{
 *     extension?: string,
 *     compile_path: string,
 *     cache_path: string,
 *     caching: bool,
 *     cache_lifetime: int,
 *     force_compile: bool,
 *     debugging: bool,
 *     escape_html?: bool,
 *     plugins_paths: list<string>,
 *     left_delimiter?: string|null,
 *     right_delimiter?: string|null,
 *     compile_check?: bool,
 *     default_modifiers?: list<string>|string,
 *     error_reporting?: int|null,
 *     security?: string|null,
 * }
 */
class SmartyFactory
{
    /**
     * @var array<int, callable(Smarty, SmartyConfig): void>
     */
    protected static array $configurators = [];

    /**
     * Register a callback that runs against every Smarty instance built by
     * this factory, after the curated config and built-in plugins are
     * applied. Use this from a service provider's boot() to reach config
     * keys we don't expose, swap the cache resource, register custom
     * plugins, or apply a security policy — anything Smarty supports.
     *
     * @param  callable(Smarty, SmartyConfig): void  $callback
     */
    public static function configure(callable $callback): void
    {
        self::$configurators[] = $callback;
    }

    /**
     * Drop all registered configurators. Intended for test teardown.
     */
    public static function flushConfigurators(): void
    {
        self::$configurators = [];
    }

    /**
     * @param  SmartyConfig  $config
     */
    public function __construct(
        protected Filesystem $files,
        protected array $config,
    ) {}

    /**
     * @param  array<int, string>  $templatePaths
     */
    public function make(array $templatePaths): BridgedSmarty
    {
        $smarty = new BridgedSmarty;

        $this->files->ensureDirectoryExists($this->config['compile_path']);
        $this->files->ensureDirectoryExists($this->config['cache_path']);

        $smarty->setTemplateDir($templatePaths);
        $smarty->setCompileDir($this->config['compile_path']);
        $smarty->setCacheDir($this->config['cache_path']);

        $smarty->setCaching(
            $this->config['caching'] ? Smarty::CACHING_LIFETIME_CURRENT : Smarty::CACHING_OFF
        );
        $smarty->setCacheLifetime($this->config['cache_lifetime']);
        $smarty->setForceCompile((bool) $this->config['force_compile']);
        $smarty->setDebugging((bool) $this->config['debugging']);
        $smarty->setEscapeHtml((bool) ($this->config['escape_html'] ?? true));
        $smarty->setCompileCheck(
            ($this->config['compile_check'] ?? true) ? Smarty::COMPILECHECK_ON : Smarty::COMPILECHECK_OFF
        );

        if (isset($this->config['left_delimiter'])) {
            $smarty->setLeftDelimiter($this->config['left_delimiter']);
        }

        if (isset($this->config['right_delimiter'])) {
            $smarty->setRightDelimiter($this->config['right_delimiter']);
        }

        if (! empty($this->config['default_modifiers'])) {
            $smarty->setDefaultModifiers($this->config['default_modifiers']);
        }

        if (isset($this->config['error_reporting'])) {
            $smarty->setErrorReporting($this->config['error_reporting']);
        }

        foreach ($this->config['plugins_paths'] as $path) {
            $smarty->addPluginsDir($path);
        }

        LaravelPlugins::register($smarty);

        if ($policy = $this->resolveSecurityPolicy($smarty)) {
            $smarty->enableSecurity($policy);
        }

        foreach (self::$configurators as $configurator) {
            $configurator($smarty, $this->config);
        }

        return $smarty;
    }

    /**
     * Resolve the configured security policy, if any. Throws when the
     * value names a class that doesn't exist or doesn't extend
     * \Smarty\Security — silent fallback to "no security" is unsafe
     * because the user assumes they're protected.
     */
    protected function resolveSecurityPolicy(Smarty $smarty): ?Security
    {
        $value = $this->config['security'] ?? null;

        if ($value === null) {
            return null;
        }

        $class = match ($value) {
            'balanced' => BalancedSecurityPolicy::class,
            'strict' => StrictSecurityPolicy::class,
            default => $value,
        };

        if (! class_exists($class)) {
            throw new InvalidArgumentException(
                "Invalid smarty.security value: expected null, 'balanced', 'strict', "
                ."or a class-string extending \\Smarty\\Security; got [{$value}]."
            );
        }

        if (! is_subclass_of($class, Security::class) && $class !== Security::class) {
            throw new InvalidArgumentException(
                "Invalid smarty.security value: class [{$class}] must extend \\Smarty\\Security."
            );
        }

        return new $class($smarty);
    }
}
