<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Lang;
use Vusys\LaravelSmarty\Tests\TestCase;

class LangChoiceTest extends TestCase
{
    public function test_lang_choice_function_and_trans_choice_modifier_pluralise(): void
    {
        Lang::addLines(['messages.apples' => '{0} no apples|[1,*] :count apples'], 'en');
        Lang::addLines(['messages.apples_named' => '[1,*] :count :fruit'], 'en');

        $output = view('lang_choice')->render();

        $this->assertStringContainsString('function_zero=no apples', $output);
        $this->assertStringContainsString('function_many=5 apples', $output);
        $this->assertStringContainsString('function_named=3 pears', $output);
        $this->assertStringContainsString('modifier_zero=no apples', $output);
        $this->assertStringContainsString('modifier_many=5 apples', $output);
        $this->assertStringContainsString('modifier_named=3 pears', $output);
    }
}
