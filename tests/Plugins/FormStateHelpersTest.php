<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Smarty\Smarty;
use Vusys\LaravelSmarty\Tests\TestCase;

class FormStateHelpersTest extends TestCase
{
    public function test_helpers_emit_attribute_when_condition_is_truthy(): void
    {
        $output = view('form_state', ['on' => true, 'off' => false])->render();

        $this->assertStringContainsString('checked=[checked]', $output);
        $this->assertStringContainsString('selected=[selected]', $output);
        $this->assertStringContainsString('disabled=[disabled]', $output);
        $this->assertStringContainsString('readonly=[readonly]', $output);
        $this->assertStringContainsString('required=[required]', $output);
    }

    public function test_helpers_emit_nothing_when_condition_is_falsy_or_absent(): void
    {
        $output = view('form_state', ['on' => true, 'off' => false])->render();

        $this->assertStringContainsString('unchecked=[]', $output);
        $this->assertStringContainsString('default=[]', $output);
    }

    public function test_helpers_are_registered_cacheable(): void
    {
        // Pure functions of their input: under smarty.caching the
        // *condition* determines cacheability, exactly as with any
        // {$var} the template prints — no reason to force a nocache
        // region around a fixed token.
        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['checked', 'selected', 'disabled', 'readonly', 'required'] as $name) {
            [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_FUNCTION, $name);
            $this->assertTrue($cacheable, "{{$name}} should register cacheable");
        }
    }
}
