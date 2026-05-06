<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Vusys\LaravelSmarty\Tests\TestCase;

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

    public function test_error_block_does_not_evaluate_body_when_field_has_no_error(): void
    {
        $output = view('error_lazy')->render();

        $this->assertSame("[start][end]\n", $output);
    }

    public function test_error_block_restores_outer_message_on_exit(): void
    {
        Session::start();
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'email' => ['inner-message'],
        ]));
        Session::put('errors', $errors);

        $output = view('error_restore', ['message' => 'outer-message'])->render();

        $this->assertStringContainsString('before=outer-message', $output);
        $this->assertStringContainsString('inner=inner-message', $output);
        $this->assertStringContainsString('after=outer-message', $output);
    }
}
