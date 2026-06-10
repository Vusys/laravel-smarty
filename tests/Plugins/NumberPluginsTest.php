<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Number;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Compile\NocacheModifierCompiler;
use Vusys\LaravelSmarty\Tests\TestCase;

class NumberPluginsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Number::class)) {
            $this->markTestSkipped('Illuminate\\Support\\Number requires Laravel 11+.');
        }
    }

    public function test_modifiers_format_via_laravel_number(): void
    {
        $output = view('numbers')->render();

        $this->assertStringContainsString('currency_default='.Number::currency(1234.56), $output);
        $this->assertStringContainsString('currency_gbp='.Number::currency(1234.56, 'GBP'), $output);
        $this->assertStringContainsString('file_size_kb='.Number::fileSize(1536), $output);
        $this->assertStringContainsString('file_size_mb='.Number::fileSize(4500000, 1), $output);
        $this->assertStringContainsString('percentage='.Number::percentage(12), $output);
        $this->assertStringContainsString('percentage_precise='.Number::percentage(12.345, 1), $output);
        $this->assertStringContainsString('abbreviate='.Number::abbreviate(1500), $output);
        $this->assertStringContainsString('abbreviate_precise='.Number::abbreviate(1500000, 1), $output);
        $this->assertStringContainsString('for_humans='.Number::forHumans(1500), $output);
    }

    public function test_number_modifiers_are_registered_nocache(): void
    {
        // Number::* formats through the app locale, so the rendered
        // strings must not be baked into the output cache. Smarty
        // ignores the cacheable flag for modifiers — the enforcement is
        // the NocacheModifierCompiler; the flag keeps introspection
        // truthful.
        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['currency', 'file_size', 'percentage', 'abbreviate', 'number_for_humans'] as $name) {
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
