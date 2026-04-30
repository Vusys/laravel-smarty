<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;
use Illuminate\Support\Facades\Session;

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
