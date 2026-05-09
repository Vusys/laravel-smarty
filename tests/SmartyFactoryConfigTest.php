<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use Smarty\Security;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Security\BalancedSecurityPolicy;
use Vusys\LaravelSmarty\Security\StrictSecurityPolicy;
use Vusys\LaravelSmarty\SmartyFactory;

class SmartyFactoryConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SmartyFactory::flushConfigurators();
    }

    protected function tearDown(): void
    {
        SmartyFactory::flushConfigurators();

        parent::tearDown();
    }

    public function test_left_and_right_delimiters_can_be_overridden(): void
    {
        $this->app['config']->set('smarty.left_delimiter', '<%');
        $this->app['config']->set('smarty.right_delimiter', '%>');

        $output = view('delimiters', ['name' => 'World'])->render();

        $this->assertSame("Hello, World!\n", $output);
    }

    public function test_default_modifiers_apply_to_every_output(): void
    {
        $this->app['config']->set('smarty.escape_html', false);
        $this->app['config']->set('smarty.default_modifiers', ['upper']);

        $output = view('hello', ['name' => 'world'])->render();

        $this->assertSame("Hello, WORLD!\n", $output);
    }

    public function test_configure_callback_runs_against_smarty(): void
    {
        SmartyFactory::configure(function (Smarty $smarty): void {
            $smarty->assign('global_user', 'configured');
        });

        $output = view('global')->render();

        $this->assertSame("Hello, configured!\n", $output);
    }

    public function test_configure_callback_receives_resolved_config(): void
    {
        $captured = null;

        SmartyFactory::configure(function (Smarty $smarty, array $config) use (&$captured): void {
            $captured = $config;
        });

        view('hello', ['name' => 'World'])->render();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('compile_path', $captured);
        $this->assertArrayHasKey('cache_path', $captured);
    }

    public function test_custom_modifier_loads_from_plugins_paths(): void
    {
        $files = new Filesystem;
        $dir = sys_get_temp_dir().'/laravel-smarty-tests/plugins-'.bin2hex(random_bytes(4));
        $files->ensureDirectoryExists($dir);
        $files->put(
            $dir.'/modifier.shout.php',
            '<?php function smarty_modifier_shout(string $v): string { return strtoupper($v).\'!\'; }',
        );

        $this->app['config']->set('smarty.plugins_paths', [$dir]);

        $files->put($this->viewsPath.'/probe_shout.tpl', '{$name|shout}');

        try {
            $output = view('probe_shout', ['name' => 'hi'])->render();

            $this->assertStringContainsString('HI!', $output);
        } finally {
            $files->delete($this->viewsPath.'/probe_shout.tpl');
            $files->deleteDirectory($dir);
        }
    }

    public function test_custom_function_plugin_loads_from_plugins_paths(): void
    {
        $files = new Filesystem;
        $dir = sys_get_temp_dir().'/laravel-smarty-tests/plugins-'.bin2hex(random_bytes(4));
        $files->ensureDirectoryExists($dir);
        $files->put(
            $dir.'/function.greet.php',
            '<?php function smarty_function_greet(array $params): string { return \'hello, \'.($params[\'name\'] ?? \'world\'); }',
        );

        $this->app['config']->set('smarty.plugins_paths', [$dir]);

        $files->put($this->viewsPath.'/probe_greet.tpl', '{greet name="ada"}');

        try {
            $output = view('probe_greet')->render();

            $this->assertStringContainsString('hello, ada', $output);
        } finally {
            $files->delete($this->viewsPath.'/probe_greet.tpl');
            $files->deleteDirectory($dir);
        }
    }

    public function test_security_defaults_to_disabled(): void
    {
        view('hello', ['name' => 'World'])->render();

        $engine = $this->app['view']->getEngineResolver()->resolve('smarty');

        $this->assertNull($engine->smarty()->security_policy);
    }

    public function test_security_balanced_alias_resolves_to_class(): void
    {
        $this->app['config']->set('smarty.security', 'balanced');

        view('hello', ['name' => 'World'])->render();

        $engine = $this->app['view']->getEngineResolver()->resolve('smarty');

        $this->assertInstanceOf(BalancedSecurityPolicy::class, $engine->smarty()->security_policy);
    }

    public function test_security_strict_alias_resolves_to_class(): void
    {
        $this->app['config']->set('smarty.security', 'strict');

        view('hello', ['name' => 'World'])->render();

        $engine = $this->app['view']->getEngineResolver()->resolve('smarty');

        $this->assertInstanceOf(StrictSecurityPolicy::class, $engine->smarty()->security_policy);
    }

    public function test_security_accepts_custom_class_string(): void
    {
        $this->app['config']->set('smarty.security', CustomTestSecurityPolicy::class);

        view('hello', ['name' => 'World'])->render();

        $engine = $this->app['view']->getEngineResolver()->resolve('smarty');

        $this->assertInstanceOf(CustomTestSecurityPolicy::class, $engine->smarty()->security_policy);
    }

    public function test_security_throws_for_unknown_class(): void
    {
        $this->app['config']->set('smarty.security', '\\This\\Class\\Does\\Not\\Exist');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/expected null.*balanced.*strict.*class-string/i');

        view('hello', ['name' => 'World'])->render();
    }

    public function test_security_throws_for_class_not_extending_smarty_security(): void
    {
        // \stdClass exists but isn't a Security subclass.
        $this->app['config']->set('smarty.security', \stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be a \*subclass\* of/i');

        view('hello', ['name' => 'World'])->render();
    }

    public function test_security_throws_for_bare_smarty_security_class(): void
    {
        // The bare \Smarty\Security activates security mode but with all
        // upstream defaults, which is more or less a no-op. Reject it
        // explicitly so users don't think they're protected when they
        // aren't.
        $this->app['config']->set('smarty.security', Security::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/base class itself is too permissive/i');

        view('hello', ['name' => 'World'])->render();
    }

    public function test_security_throws_for_non_string_value(): void
    {
        // bool / int / array etc. all fail the up-front is_string() guard
        // with a friendly error rather than slipping through to a confusing
        // class_exists() failure.
        $this->app['config']->set('smarty.security', true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/got \[bool\]/');

        view('hello', ['name' => 'World'])->render();
    }

    public function test_compiled_template_survives_security_toggle(): void
    {
        // Documents the hazard called out in the README: toggling
        // smarty.security after a template was already compiled does NOT
        // re-compile the cached output, so the bypassed call is baked in
        // until compile_path is cleared. This regression test pins the
        // documented behaviour — if it ever changes (e.g. Smarty starts
        // hashing the policy into the compile filename) we want to know.
        $this->app['config']->set('smarty.force_compile', false);

        $tpl = $this->viewsPath.'/security_toggle.tpl';
        (new Filesystem)->put($tpl, '{$x|upper}'."\n");

        try {
            // First render: no security. Compiled bytecode contains the
            // raw upper() call.
            $first = view('security_toggle', ['x' => 'hi'])->render();
            $this->assertSame("HI\n", $first);

            // Now flip on Strict. Without clearing compile_path, the
            // existing compiled file is reused.
            $this->app['config']->set('smarty.security', 'strict');

            // The render still succeeds because the policy gate only fires
            // at compile time, not against already-compiled code. This
            // *would* have been blocked if compiled fresh under Strict.
            $second = view('security_toggle', ['x' => 'hi'])->render();
            $this->assertSame("HI\n", $second);
        } finally {
            (new Filesystem)->delete($tpl);
        }
    }

    public function test_strict_security_is_orthogonal_to_escape_html(): void
    {
        // Strict gates tags and modifiers at compile time; escape_html
        // gates {$var} output through htmlspecialchars at render time.
        // Confirm the two settings don't interfere — Strict still blocks
        // {php} when escape is off, and a normal var renders raw.
        $this->app['config']->set('smarty.escape_html', false);
        $this->app['config']->set('smarty.security', 'strict');

        $tpl = $this->viewsPath.'/security_no_escape.tpl';
        (new Filesystem)->put($tpl, '{$payload}'."\n");

        try {
            $output = view('security_no_escape', ['payload' => '<b>raw</b>'])->render();
            $this->assertSame("<b>raw</b>\n", $output);
        } finally {
            (new Filesystem)->delete($tpl);
        }

        // Same engine, different fixture: {php} is still rejected.
        $this->expectException(ViewException::class);
        view('security_php')->render();
    }

    public function test_custom_block_plugin_loads_from_plugins_paths(): void
    {
        $files = new Filesystem;
        $dir = sys_get_temp_dir().'/laravel-smarty-tests/plugins-'.bin2hex(random_bytes(4));
        $files->ensureDirectoryExists($dir);
        $files->put(
            $dir.'/block.banner.php',
            '<?php function smarty_block_banner(array $params, ?string $content, $template, &$repeat): string {'
            .' if ($content === null) { return \'\'; } return \'[banner]\'.$content.\'[/banner]\'; }',
        );

        $this->app['config']->set('smarty.plugins_paths', [$dir]);

        $files->put($this->viewsPath.'/probe_banner.tpl', '{banner}hi{/banner}');

        try {
            $output = view('probe_banner')->render();

            $this->assertStringContainsString('[banner]hi[/banner]', $output);
        } finally {
            $files->delete($this->viewsPath.'/probe_banner.tpl');
            $files->deleteDirectory($dir);
        }
    }
}

class CustomTestSecurityPolicy extends Security {}
