<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Session;
use Vusys\LaravelSmarty\Tests\TestCase;

class CsrfPluginsTest extends TestCase
{
    public function test_csrf_field_emits_hidden_token_input(): void
    {
        Session::start();
        $token = Session::token();

        $output = view('csrf_field')->render();

        $this->assertStringContainsString('<input type="hidden" name="_token" value="'.$token.'"', $output);
    }

    public function test_csrf_token_emits_raw_token(): void
    {
        Session::start();
        $token = Session::token();

        $output = view('csrf_token')->render();

        $this->assertStringContainsString('<meta name="csrf-token" content="'.$token.'">', $output);
        $this->assertStringNotContainsString('<input', $output);
    }
}
