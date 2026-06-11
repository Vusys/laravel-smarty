<?php

declare(strict_types=1);

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

    public function test_array_config_and_session_render_empty_inline_but_assign_intact(): void
    {
        // An array value emitted inline would echo "Array" and raise a PHP
        // "Array to string conversion" warning; it should render '' instead.
        // `assign=` is the supported path for pulling the array into scope.
        Config::set('app.array_val', ['x', 'y']);
        Config::set('app.scalar_val', 'PINNED');
        Session::start();
        Session::put('array_val', ['a', 'b']);

        $output = view('config_session_array')->render();

        $this->assertStringContainsString('inline_config=[]', $output);
        $this->assertStringContainsString('inline_session=[]', $output);
        // An `assign=` tag emits nothing inline even for a scalar value —
        // the value goes to the variable, not the output.
        $this->assertStringContainsString('assign_scalar=[]', $output);
        $this->assertStringContainsString('assigned_config=x-y', $output);
        $this->assertStringContainsString('assigned_scalar=PINNED', $output);
        $this->assertStringNotContainsString('Array', $output);
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
