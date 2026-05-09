<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Security;

use Illuminate\View\ViewException;
use Vusys\LaravelSmarty\Security\StrictSecurityPolicy;
use Vusys\LaravelSmarty\Tests\TestCase;

class StrictSecurityPolicyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smarty.security', 'strict');
    }

    public function test_engine_has_strict_policy_attached(): void
    {
        view('security_ok', ['name' => 'world'])->render();

        $engine = $this->app['view']->getEngineResolver()->resolve('smarty');

        $this->assertInstanceOf(
            StrictSecurityPolicy::class,
            $engine->smarty()->security_policy,
        );
    }

    public function test_inherited_balanced_blocks_still_apply(): void
    {
        $this->expectException(ViewException::class);

        view('security_php')->render();
    }

    public function test_fetch_tag_is_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/fetch.*not allowed|disabled/i');

        view('security_fetch')->render();
    }

    public function test_constants_are_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/constants/i');

        view('security_constant')->render();
    }

    public function test_modifier_outside_allowlist_is_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/regex_replace|modifier/i');

        view('security_regex_replace', ['value' => 'xyz'])->render();
    }

    public function test_allowlisted_built_in_modifier_works(): void
    {
        // `upper` is on the allow-list — confirms allow-list isn't a blanket block.
        $output = view('security_ok', ['name' => 'world'])->render();

        $this->assertStringContainsString('Hello, WORLD!', $output);
    }

    public function test_renamed_built_in_modifier_works(): void
    {
        // `wordwrap` is the real Smarty 5 name (not `word_wrap`). Locks the
        // allow-list rename in so a future drift fails here, not in user code.
        $output = view('security_strict_wordwrap', ['value' => 'one two three four'])->render();

        $this->assertSame("one|two|three|four\n", $output);
    }

    public function test_allowlisted_package_modifier_works(): void
    {
        // `markdown` is registered by HelperPlugins and must stay on the
        // allow-list — if it ever gets dropped, this test fails before users do.
        // Asserts real rendered output, not just absence of the modifier name.
        $output = view('security_strict_package_modifier', ['content' => '**bold**'])->render();

        $this->assertStringContainsString('<strong>bold</strong>', $output);
    }
}
