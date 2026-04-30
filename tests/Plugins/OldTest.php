<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;
use Illuminate\Support\Facades\Session;

class OldTest extends TestCase
{
    public function test_old_returns_default_when_no_flashed_input(): void
    {
        $output = view('old')->render();

        $this->assertStringContainsString('email=fallback@example.com', $output);
    }

    public function test_old_returns_flashed_input_when_present(): void
    {
        Session::start();
        Session::flashInput(['email' => 'previous@example.com']);
        $this->app['request']->setLaravelSession($this->app['session.store']);

        $output = view('old')->render();

        $this->assertStringContainsString('email=previous@example.com', $output);
    }
}
