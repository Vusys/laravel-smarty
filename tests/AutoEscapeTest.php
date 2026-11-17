<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Stringable;

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

    /**
     * An Htmlable is already HTML, as in Blade's {{ }} (#68): printing a
     * paginator's links() or an HtmlString must not escape it again.
     */
    public function test_an_htmlable_is_printed_as_html(): void
    {
        $output = view('escape', ['payload' => new HtmlString('<b>x</b>')])->render();

        $this->assertStringContainsString('<b>x</b>', $output);
        $this->assertStringNotContainsString('&lt;b&gt;', $output);
    }

    public function test_paginator_links_print_as_html_in_a_template(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 50, 10, 2, ['path' => 'http://localhost/users']);

        $output = view('escape_links', ['paginator' => $paginator])->render();

        $this->assertStringContainsString('<nav role="navigation"', $output);
        $this->assertStringContainsString('href="http://localhost/users?page=3"', $output);
        $this->assertStringNotContainsString('&lt;nav', $output);
    }

    /**
     * Only an Htmlable is trusted. A plain Stringable, or a modifier that turns
     * the Htmlable into a string, is escaped as before.
     */
    public function test_strings_and_stringables_are_still_escaped(): void
    {
        $stringable = new class implements Stringable
        {
            public function __toString(): string
            {
                return '<b>x</b>';
            }
        };

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', view('escape', ['payload' => $stringable])->render());
        $this->assertStringContainsString(
            'value=[<i>y</i>] modified=[&lt;I&gt;Y&lt;/I&gt;]',
            view('escape_htmlable', ['payload' => new HtmlString('<i>y</i>')])->render(),
        );
    }
}
