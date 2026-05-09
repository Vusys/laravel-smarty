<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Globals;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Vusys\LaravelSmarty\Tests\TestCase;

class ErrorsIntegrationTest extends TestCase
{
    public function test_template_iterates_default_bag(): void
    {
        Session::start();
        $bag = (new ViewErrorBag)
            ->put('default', new MessageBag([
                'email' => ['Email is required.'],
                'name' => ['Name is required.'],
            ]))
            ->put('login', new MessageBag([
                'password' => ['Password is invalid.'],
            ]));
        Session::put('errors', $bag);

        $output = view('globals_errors')->render();

        $this->assertStringContainsString('<li>Email is required.</li>', $output);
        $this->assertStringContainsString('<li>Name is required.</li>', $output);
        $this->assertStringContainsString('first-email=Email is required.', $output);
        $this->assertStringContainsString('login-bag-any=yes', $output);
        $this->assertStringNotContainsString('no-errors', $output);
    }

    public function test_template_handles_no_errors(): void
    {
        $output = view('globals_errors')->render();

        $this->assertStringContainsString('no-errors', $output);
        $this->assertStringContainsString('first-email=', $output);
        $this->assertStringContainsString('login-bag-any=no', $output);
    }
}
