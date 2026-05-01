<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Js;
use Vusys\LaravelSmarty\Tests\TestCase;

class JsonModifierTest extends TestCase
{
    public function test_json_modifier_safely_encodes_for_javascript(): void
    {
        $payload = ['snippet' => '</script><script>alert(1)</script>'];

        $output = view('json', ['payload' => $payload])->render();

        $expected = (string) Js::from($payload);
        $this->assertStringContainsString('var x = '.$expected.';', $output);
        $this->assertStringNotContainsString('</script>', $output);
    }

    public function test_json_modifier_matches_js_from_output(): void
    {
        $payload = ['name' => 'Ada', 'count' => 3];

        $output = view('json', ['payload' => $payload])->render();

        $this->assertSame('var x = '.(Js::from($payload)).";\n", $output);
    }
}
