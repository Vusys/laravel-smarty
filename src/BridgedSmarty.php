<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty;

use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Smarty\Smarty;
use Smarty\Template;
use Vusys\LaravelSmarty\Debug\LineTrackingCompiler;

/**
 * Smarty subclass that fires Laravel view events for every sub-template
 * created via {extends} and {include}, and injects our LineTrackingCompiler
 * onto each Template before any compile call runs. Both responsibilities
 * piggy-back on doCreateTemplate(), the single chokepoint for Template
 * instantiation in Smarty's runtime.
 *
 * Compiler injection works because Template::getCompiler() lazy-initialises
 * its private $compiler field via Source::createCompiler(); pre-populating
 * that field via reflection short-circuits the lazy init and bypasses
 * Source's hard-coded `new \Smarty\Compiler\Template($this->smarty)` with
 * no need to patch vendor code.
 */
class BridgedSmarty extends Smarty
{
    protected ?SmartyResource $resource = null;

    private static ?ReflectionProperty $compilerField = null;

    public function setResource(SmartyResource $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * Verify Smarty's Template class still exposes the private $compiler
     * field we reflect into. Called from the service provider at boot so a
     * Smarty release that renames or relocates the field fails loudly
     * instead of silently shipping unannotated compiles.
     */
    public static function ensureCompilerInjectionAvailable(): void
    {
        $class = new ReflectionClass(Template::class);
        if (! $class->hasProperty('compiler')) {
            throw new RuntimeException(
                'Smarty\\Template no longer declares a $compiler property; '.
                'LineTrackingCompiler injection is unavailable. This package '.
                'needs an update to track the new Smarty internals.'
            );
        }
    }

    /**
     * @param  string|Template|null  $resource_name
     * @param  mixed  $cache_id
     * @param  mixed  $compile_id
     * @param  Smarty|Template|null  $parent
     * @param  int|null  $caching
     * @param  int|null  $cache_lifetime
     * @param  array<string, mixed>  $data
     */
    public function doCreateTemplate(
        $resource_name,
        $cache_id = null,
        $compile_id = null,
        $parent = null,
        $caching = null,
        $cache_lifetime = null,
        bool $isConfig = false,
        array $data = [],
    ): Template {
        $tpl = parent::doCreateTemplate(
            $resource_name, $cache_id, $compile_id, $parent, $caching, $cache_lifetime, $isConfig, $data,
        );

        // Config templates use a different compiler class (Configfile); leave
        // those untouched. Source-template compilers we replace wholesale.
        if (! $isConfig) {
            $this->compilerField()->setValue($tpl, new LineTrackingCompiler($this));
        }

        // $parent is a Template only when Smarty is loading a sub-template
        // (extends parent or include partial). When called directly from the
        // SmartyEngine entry point, $parent is the Smarty instance itself, so
        // we don't fire — Laravel already fired events for the entry view.
        if ($parent instanceof Template && $this->resource instanceof SmartyResource && ! $isConfig) {
            $this->resource->fireForTemplate($tpl);
        }

        return $tpl;
    }

    private function compilerField(): ReflectionProperty
    {
        if (! self::$compilerField instanceof ReflectionProperty) {
            self::$compilerField = new ReflectionProperty(Template::class, 'compiler');
        }

        return self::$compilerField;
    }
}
