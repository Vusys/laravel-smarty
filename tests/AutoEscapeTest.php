<?php

namespace Vusys\LaravelSmarty\Tests;

class AutoEscapeTest extends TestCase
{
    public function test_html_is_escaped_by_default(): void
    {
        $output = view('escape', ['payload' => '<b>x</b>'])->render();

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $output);
        $this->assertStringNotContainsString('<b>x</b>', $output);
    }

    public function test_escape_html_can_be_disabled(): void
    {
        $this->app['config']->set('smarty.escape_html', false);

        $output = view('escape', ['payload' => '<b>x</b>'])->render();

        $this->assertStringContainsString('<b>x</b>', $output);
    }
}
