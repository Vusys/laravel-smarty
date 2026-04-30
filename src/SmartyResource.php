<?php

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
        $path = $tpl->getSource()->getFilepath();

        if ($path === null || $path === '') {
            return;
        }

        $name = $this->deriveViewName($tpl->getSmarty(), $path);
        $view = new View($this->factory, $this->engine, $name, $path);

        $this->events->dispatch('creating: '.$name, [$view]);
        $this->events->dispatch('composing: '.$name, [$view]);
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
        $relative = preg_replace('/\.'.preg_quote($this->extension, '/').'$/', '', $relative);

        return str_replace('/', '.', $relative);
    }
}
