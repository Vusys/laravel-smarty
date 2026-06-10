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

    public function test_old_output_is_escaped(): void
    {
        // old() round-trips the user's previous submission and function
        // plugins bypass escape_html — unescaped, a failed validation
        // reflects markup straight back into the form (XSS).
        Session::start();
        Session::flashInput(['email' => '"><script>alert(1)</script>']);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $output = view('old')->render();

        $this->assertStringContainsString('email=&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    public function test_old_escapes_even_when_escape_html_is_disabled(): void
    {
        // The escaping is per-plugin, not a side effect of escape_html —
        // matching Blade, where {{ old(...) }} escapes unconditionally.
        $this->app['config']->set('smarty.escape_html', false);
        Session::start();
        Session::flashInput(['email' => '<b>x</b>']);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $output = view('old')->render();

        $this->assertStringContainsString('email=&lt;b&gt;x&lt;/b&gt;', $output);
    }

    public function test_old_raw_param_opts_out_of_escaping(): void
    {
        Session::start();
        Session::flashInput(['email' => '<b>kept</b>']);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $output = view('old_raw')->render();

        $this->assertStringContainsString('email=<b>kept</b>', $output);
    }

    public function test_old_array_input_renders_empty(): void
    {
        // Array form fields (emails[]) flash an array — there's no single
        // printable value, so {old} renders nothing rather than "Array"
        // plus a conversion warning.
        Session::start();
        Session::flashInput(['emails' => ['a@example.com', 'b@example.com']]);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $output = view('old_array')->render();

        $this->assertStringContainsString('emails=[]', $output);
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
