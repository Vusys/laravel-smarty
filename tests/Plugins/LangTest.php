<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Lang;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Tests\TestCase;

class LangTest extends TestCase
{
    public function test_lang_function_and_trans_modifier_translate_keys(): void
    {
        Lang::addLines(['messages.welcome' => 'Hello, :name!'], 'en');

        $output = view('lang')->render();

        $this->assertStringContainsString('function=Hello, Bryan!', $output);
        $this->assertStringContainsString('modifier=Hello, Bryan!', $output);
    }

    public function test_lang_function_is_registered_uncached(): void
    {
        // App locale shifts per-request (middleware-driven, user prefs);
        // caching the rendered translation would freeze whatever locale
        // produced the first cache write. Both {lang} and {lang_choice}
        // must register with cacheable=false.
        Lang::addLines(['messages.welcome' => 'Hi'], 'en');
        view('lang')->render();

        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['lang', 'lang_choice'] as $name) {
            [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_FUNCTION, $name);
            $this->assertFalse($cacheable, "{{$name}} must register with cacheable=false");
        }
    }
}
