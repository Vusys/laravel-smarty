<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Session;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Tests\TestCase;

class OldTest extends TestCase
{
    public function test_old_returns_default_when_no_flashed_input(): void
    {
        $output = view('old')->render();

        $this->assertStringContainsString('email=fallback@example.com', $output);
    }

    public function test_old_returns_flashed_input_when_present(): void
    {
        Session::start();
        Session::flashInput(['email' => 'previous@example.com']);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $output = view('old')->render();

        $this->assertStringContainsString('email=previous@example.com', $output);
    }

    public function test_old_is_registered_uncached(): void
    {
        // Flashed input survives exactly one request — caching the
        // rendered value would pin it across renders and resurrect the
        // user's last failed submission on every subsequent page load.
        view('old')->render();

        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_FUNCTION, 'old');
        $this->assertFalse($cacheable);
    }
}
