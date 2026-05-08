<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Vusys\LaravelSmarty\Exceptions\ReservedTemplateVariable;
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

    public function test_shared_session_renders_real_session_value(): void
    {
        Session::start();
        Session::put('status', 'real session');

        $output = view('shared_session_override')->render();

        $this->assertStringContainsString('status=real session', $output);
    }

    public function test_passing_session_as_view_data_throws_reserved_variable(): void
    {
        Session::start();

        $this->expectException(ReservedTemplateVariable::class);

        view('shared_session_override', ['session' => ['status' => 'override']])->render();
    }
}
