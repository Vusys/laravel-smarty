<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Engine;
use Illuminate\View\Factory;
use Illuminate\View\View;
use Smarty\Smarty;
use Smarty\Template;

/**
 * Fires Laravel view events for every Smarty sub-template loaded via {extends}
 * or {include}, so `composing:`/`creating:` listeners — including Debugbar's
 * ViewCollector and any user-registered view composers — observe the full
 * template tree on every render, matching Blade's behavior.
 *
 * Data composers add via `$view->with(...)` is transcribed onto the actual
 * Smarty\Template after dispatch, so `View::composer($layoutName, ...)`
 * produces Blade-equivalent semantics for `{extends}`-pulled layouts and
 * `{include}`-d partials. Without that step the synthetic `View` we hand
 * the dispatcher would accept the writes and then be garbage-collected,
 * silently dropping the composer's data.
 *
 * Driven by BridgedSmarty::doCreateTemplate(), which runs on every render
 * (the compile cache only short-circuits source loading, not template
 * instantiation).
 */
class SmartyResource
{
    public function __construct(
        protected Factory $factory,
        protected Dispatcher $events,
        protected Engine $engine,
        protected string $extension,
    ) {}

    public function fireForTemplate(Template $tpl): void
    {
        $source = $tpl->getSource();
        $path = $source?->getFilepath();

        if ($path === null || $path === '') {
            return;
        }

        $name = $this->deriveViewName($tpl->getSmarty(), $path);
        $view = new View($this->factory, $this->engine, $name, $path);

        $this->events->dispatch('creating: '.$name, [$view]);
        $this->events->dispatch('composing: '.$name, [$view]);

        // Transcribe data the listeners wrote via $view->with(...) onto the
        // Template that's about to render. Without this hop, composer
        // writes land on the synthetic $view above (never rendered) and
        // disappear — diverging from Blade, where @extends-pulled
        // layouts run through View::make() and composer data propagates
        // naturally.
        foreach ($view->getData() as $key => $value) {
            $tpl->assign($key, $value);
        }
    }

    protected function deriveViewName(Smarty $smarty, string $path): string
    {
        $path = str_replace('\\', '/', $path);

        foreach ((array) $smarty->getTemplateDir() as $directory) {
            $prefix = str_replace('\\', '/', rtrim((string) $directory, '/\\')).'/';
            if (str_starts_with($path, $prefix)) {
                return $this->logicalName(substr($path, strlen($prefix)));
            }
        }

        return $this->logicalName(basename($path));
    }

    protected function logicalName(string $relative): string
    {
        $relative = preg_replace('/\.'.preg_quote($this->extension, '/').'$/', '', $relative) ?? $relative;

        return str_replace('/', '.', $relative);
    }
}
