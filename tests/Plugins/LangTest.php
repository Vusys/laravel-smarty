<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Lang;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Compile\NocacheModifierCompiler;
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

    public function test_lang_function_output_is_escaped(): void
    {
        // Replacement values are interpolated by __() and echoed by a
        // function plugin, which bypasses escape_html — so {lang} escapes
        // its own output. raw=true opts out for trusted HTML lines.
        Lang::addLines(['messages.welcome' => 'Hello, :name!'], 'en');

        $output = view('lang_escape', ['name' => '<b>Bryan</b>'])->render();

        $this->assertStringContainsString('escaped=Hello, &lt;b&gt;Bryan&lt;/b&gt;!', $output);
        $this->assertStringContainsString('raw=Hello, <b>Bryan</b>!', $output);
    }

    public function test_lang_function_and_trans_modifier_produce_identical_bytes(): void
    {
        // {lang key=$k name=$v} and {$k|trans:['name' => $v]} are the same
        // operation through two syntaxes — their escaping must agree, or
        // template authors get different output depending on which they
        // picked. The `&` in the payload catches double-encoding drift.
        Lang::addLines(['messages.welcome' => 'Hello, :name!'], 'en');

        $output = view('lang_parity', ['name' => '<b>&Bryan</b>'])->render();

        $this->assertSame(1, preg_match('/^function=(.*)$/m', $output, $function));
        $this->assertSame(1, preg_match('/^modifier=(.*)$/m', $output, $modifier));
        $this->assertNotSame('', $function[1]);
        $this->assertSame($function[1], $modifier[1]);
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

    public function test_trans_modifiers_are_registered_nocache(): void
    {
        // Locale shifts per-request, same as {lang}. Smarty *ignores*
        // the cacheable flag for modifiers, so the operative half of
        // this contract is the NocacheModifierCompiler the extension
        // provides — the flag assertion keeps introspection truthful.
        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['trans', 'trans_choice'] as $name) {
            [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_MODIFIER, $name);
            $this->assertFalse($cacheable, "|{$name} must register with cacheable=false");
            $this->assertInstanceOf(
                NocacheModifierCompiler::class,
                $smarty->getModifierCompiler($name),
                "|{$name} must compile through NocacheModifierCompiler — the registration flag alone does nothing for modifiers.",
            );
        }
    }
}
