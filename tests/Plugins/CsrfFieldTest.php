<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Session;
use Vusys\LaravelSmarty\Tests\TestCase;

class CsrfFieldTest extends TestCase
{
    public function test_csrf_field_emits_hidden_token_input(): void
    {
        Session::start();
        $token = Session::token();

        $output = view('csrf')->render();

        $this->assertStringContainsString('<input type="hidden" name="_token" value="'.$token.'"', $output);
    }
}
