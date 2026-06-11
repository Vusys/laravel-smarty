<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Session;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Tests\TestCase;

class CsrfPluginsTest extends TestCase
{
    public function test_csrf_field_emits_hidden_token_input(): void
    {
        Session::start();
        $token = Session::token();

        $output = view('csrf_field')->render();

        $this->assertStringContainsString('<input type="hidden" name="_token" value="'.$token.'"', $output);
    }

    public function test_csrf_token_emits_raw_token(): void
    {
        Session::start();
        $token = Session::token();

        $output = view('csrf_token')->render();

        $this->assertStringContainsString('<meta name="csrf-token" content="'.$token.'">', $output);
        $this->assertStringNotContainsString('<input', $output);
    }

    public function test_csrf_helpers_are_registered_uncached(): void
    {
        // CSRF tokens rotate per-session; caching the rendered field/meta
        // would freeze the first request's token into the output cache
        // and break form posts on subsequent renders. Both csrf helpers
        // must register with cacheable=false.
        Session::start();
        view('csrf_field')->render();

        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['csrf_field', 'csrf_token'] as $name) {
            [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_FUNCTION, $name);
            $this->assertFalse($cacheable, "{{$name}} must register with cacheable=false");
        }
    }
}
