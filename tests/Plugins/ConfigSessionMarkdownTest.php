<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Vusys\LaravelSmarty\Tests\TestCase;

class ConfigSessionMarkdownTest extends TestCase
{
    public function test_config_session_and_markdown_helpers(): void
    {
        Config::set('app.name', 'Smarty Test');
        Session::start();
        Session::put('status', 'saved!');

        $output = view('config_session_markdown')->render();

        $this->assertStringContainsString('app_name=Smarty Test', $output);
        $this->assertStringContainsString('missing=fallback', $output);
        $this->assertStringContainsString('flash=saved!', $output);
        $this->assertStringContainsString('flash_default=nope', $output);
        $this->assertStringContainsString('assigned=saved!', $output);
        $this->assertStringContainsString('shared=saved!', $output);
        $this->assertStringContainsString('markdown=<p><strong>bold</strong></p>', $output);
    }

    public function test_shared_session_can_be_overridden_by_view_data(): void
    {
        Session::start();
        Session::put('status', 'real session');

        $output = view('shared_session_override', ['session' => ['status' => 'override']])->render();

        $this->assertStringContainsString('status=override', $output);
    }
}
