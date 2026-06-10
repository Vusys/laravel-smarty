<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;

class MarkdownModifierTest extends TestCase
{
    public function test_markdown_renders_unescaped_without_nofilter(): void
    {
        // The modifier compiler marks the expression raw, so the rendered
        // HTML survives escape_html without a `nofilter` opt-out.
        $output = view('markdown', ['content' => '**bold**'])->render();

        $this->assertStringContainsString('out=<p><strong>bold</strong></p>', $output);
    }

    public function test_markdown_escapes_embedded_html(): void
    {
        // CommonMark's default html_input=allow would pass this through
        // verbatim — and since the output is emitted raw, that would be
        // direct HTML injection. html_input=escape neutralizes it while
        // the markdown markup still renders.
        $output = view('markdown', ['content' => '**b** <script>alert(1)</script>'])->render();

        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringContainsString('<strong>b</strong>', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    public function test_markdown_strips_unsafe_links(): void
    {
        $output = view('markdown', ['content' => '[click](javascript:alert(1))'])->render();

        $this->assertStringContainsString('click', $output);
        $this->assertStringNotContainsString('javascript:', $output);
    }
}
