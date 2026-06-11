<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Security;

use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\DataProvider;
use Smarty\Smarty;
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
        $this->expectExceptionMessageMatches('/fetch.*(not allowed|disabled)/i');

        view('security_fetch')->render();
    }

    public function test_eval_tag_is_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/eval.*(not allowed|disabled)/i');

        view('security_eval', ['body' => 'hello'])->render();
    }

    public function test_stream_resource_is_blocked(): void
    {
        // streams=null means every stream wrapper (http/data/php/phar/...)
        // is rejected when used as a resource type via {include 'foo:...'}.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches("/stream 'data' not allowed/i");

        view('security_stream')->render();
    }

    public function test_constants_are_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/constants/i');

        view('security_constant')->render();
    }

    /**
     * The package's own state-reaching tags defeat the sandbox if left
     * reachable: {config} leaks APP_KEY/DB credentials, {service} resolves
     * arbitrary container bindings into variables with unrestricted
     * method-call access, {session} reads arbitrary session state, and
     * {dump}/{dd} disclose internals. All five are compile-time banned.
     */
    /**
     * @return array<string, array{string, string}>
     */
    public static function bannedPackageTagProvider(): array
    {
        return [
            'config' => ['config', 'security_strict_config'],
            'service' => ['service', 'security_strict_service'],
            'session' => ['session', 'security_strict_session'],
            'dump' => ['dump', 'security_strict_dump'],
            'dd' => ['dd', 'security_strict_dd'],
        ];
    }

    #[DataProvider('bannedPackageTagProvider')]
    public function test_package_state_tags_are_blocked(string $tag, string $view): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches("/tag '{$tag}' disabled by security setting/i");

        view($view)->render();
    }

    public function test_every_package_modifier_is_on_the_allow_list(): void
    {
        // Sync guard for the "(sync with src/Plugins/*)" block in
        // StrictSecurityPolicy::$allowed_modifiers: every modifier the
        // package registers must be allow-listed, or Strict templates
        // throw on first-party helpers (the way `feature_active` once
        // did). A newly added Plugins/* modifier fails here, not in
        // user templates.
        view('security_ok', ['name' => 'world'])->render();

        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        $registered = array_keys($smarty->registered_plugins[Smarty::PLUGIN_MODIFIER] ?? []);
        $this->assertNotEmpty($registered);
        $this->assertContains('feature_active', $registered, 'laravel/pennant dev-dependency missing?');

        $allowed = (new StrictSecurityPolicy($smarty))->allowed_modifiers;

        foreach ($registered as $modifier) {
            $this->assertContains(
                $modifier,
                $allowed,
                "Package modifier '{$modifier}' is registered but missing from StrictSecurityPolicy::\$allowed_modifiers.",
            );
        }
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
