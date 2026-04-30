<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;
use Illuminate\Support\Facades\Lang;

class LangTest extends TestCase
{
    public function test_lang_function_and_trans_modifier_translate_keys(): void
    {
        Lang::addLines(['messages.welcome' => 'Hello, :name!'], 'en');

        $output = view('lang')->render();

        $this->assertStringContainsString('function=Hello, Bryan!', $output);
        $this->assertStringContainsString('modifier=Hello, Bryan!', $output);
    }
}
