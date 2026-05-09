<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Filesystem\Filesystem;
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
        $this->expectExceptionMessageMatches('/must extend.*Smarty.*Security/i');

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
