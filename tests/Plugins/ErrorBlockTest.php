<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ViewErrorBag;

class ErrorBlockTest extends TestCase
{
    public function test_error_block_renders_when_field_has_error(): void
    {
        Session::start();
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'email' => ['The email is required.'],
        ]));
        Session::put('errors', $errors);

        $output = view('error')->render();

        $this->assertStringContainsString('<p class="err">The email is required.</p>', $output);
    }

    public function test_error_block_renders_nothing_when_field_has_no_error(): void
    {
        $output = view('error')->render();

        $this->assertSame("[start][end]\n", $output);
    }
}
