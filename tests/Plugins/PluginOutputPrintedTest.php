<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\HtmlString;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use TypeError;
use Vusys\LaravelSmarty\Plugins\PluginOutput;

/**
 * PluginOutput::printed(), what every escaped print tag calls (#68).
 */
final class PluginOutputPrintedTest extends TestCase
{
    public function test_an_htmlable_is_returned_as_html(): void
    {
        $this->assertSame('<b>x</b>', PluginOutput::printed(new HtmlString('<b>x</b>')));
    }

    public function test_strings_scalars_and_null_are_escaped_like_htmlspecialchars(): void
    {
        $this->assertSame('&lt;b&gt; &amp; &#039;', PluginOutput::printed("<b> & '"));
        $this->assertSame('42', PluginOutput::printed(42));
        $this->assertSame('1.5', PluginOutput::printed(1.5));
        $this->assertSame('1', PluginOutput::printed(true));
        $this->assertSame('', PluginOutput::printed(false));
        $this->assertSame('', PluginOutput::printed(null));
    }

    public function test_the_flags_and_charset_are_passed_through(): void
    {
        $this->assertSame("'", PluginOutput::printed("'", ENT_NOQUOTES, 'UTF-8'));
    }

    public function test_a_stringable_is_escaped(): void
    {
        $value = new class implements Stringable
        {
            public function __toString(): string
            {
                return '<i>';
            }
        };

        $this->assertSame('&lt;i&gt;', PluginOutput::printed($value));
    }

    public function test_an_array_prints_as_the_cast_did(): void
    {
        $this->assertSame('Array', PluginOutput::printed([1, 2]));
    }

    public function test_an_unprintable_object_says_so(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Cannot print a stdClass in a template.');

        PluginOutput::printed(new stdClass);
    }
}
