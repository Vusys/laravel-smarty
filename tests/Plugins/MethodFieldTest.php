<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;

class MethodFieldTest extends TestCase
{
    public function test_method_field_emits_hidden_method_input(): void
    {
        $output = view('method')->render();

        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT"', $output);
    }
}
