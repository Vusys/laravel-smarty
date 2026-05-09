<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use ReflectionMethod;
use Vusys\LaravelSmarty\Plugins\HtmlPlugins;
use Vusys\LaravelSmarty\Tests\TestCase;

class HtmlPluginsTest extends TestCase
{
    public function test_extract_array_returns_empty_when_param_is_not_array(): void
    {
        // Smarty templates can pass anything as `array=...`; if a caller
        // accidentally hands in a scalar, we degrade silently to an empty
        // class/style list rather than fatalling on a type error.
        $method = new ReflectionMethod(HtmlPlugins::class, 'extractArray');

        $this->assertSame([], $method->invoke(null, ['array' => 42]));
        $this->assertSame([], $method->invoke(null, ['array' => 'not-an-array']));
        $this->assertSame([], $method->invoke(null, []));
    }

    public function test_class_emits_truthy_keys_only_and_style_emits_truthy_styles(): void
    {
        $output = view('html', [
            'isPrimary' => true,
            'isActive' => false,
            'error' => true,
            'emphasised' => false,
        ])->render();

        $this->assertStringContainsString('class=<button class="btn btn-primary btn-disabled">', $output);
        $this->assertStringContainsString('style=<div style="color: red;">', $output);
        $this->assertStringContainsString('empty=<span class="">', $output);
    }

    public function test_class_drops_falsy_branches(): void
    {
        $output = view('html', [
            'isPrimary' => false,
            'isActive' => true,
            'error' => false,
            'emphasised' => true,
        ])->render();

        $this->assertStringContainsString('class=<button class="btn">', $output);
        $this->assertStringContainsString('style=<div style="font-weight: bold;">', $output);
    }
}
