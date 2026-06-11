<?php

declare(strict_types=1);

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

    public function test_nofilter_opts_a_single_expression_out(): void
    {
        // The per-expression escape hatch (Blade's {!! !!}). The same
        // payload must come out both ways in one render — escaped by
        // default, raw where the author explicitly said so.
        $output = view('escape_nofilter', ['payload' => '<b>x</b>'])->render();

        $this->assertStringContainsString('escaped=&lt;b&gt;x&lt;/b&gt;', $output);
        $this->assertStringContainsString('raw=<b>x</b>', $output);
    }
}
