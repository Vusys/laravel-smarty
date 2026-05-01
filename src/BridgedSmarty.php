<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty;

use Smarty\Smarty;
use Smarty\Template;

/**
 * Smarty subclass that fires Laravel view events for every sub-template
 * created via {extends} and {include}. Hooks doCreateTemplate(), which
 * runs on every render — not just when the compile cache is cold — so
 * Debugbar's ViewCollector and any user-registered view composers see
 * the full template tree on every request.
 */
class BridgedSmarty extends Smarty
{
    protected ?SmartyResource $resource = null;

    public function setResource(SmartyResource $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * @param  string|\Smarty\Template|null  $resource_name
     * @param  mixed  $cache_id
     * @param  mixed  $compile_id
     * @param  \Smarty\Smarty|\Smarty\Template|null  $parent
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

        // $parent is a Template only when Smarty is loading a sub-template
        // (extends parent or include partial). When called directly from the
        // SmartyEngine entry point, $parent is the Smarty instance itself, so
        // we don't fire — Laravel already fired events for the entry view.
        if ($parent instanceof Template && $this->resource instanceof SmartyResource && ! $isConfig) {
            $this->resource->fireForTemplate($tpl);
        }

        return $tpl;
    }
}
