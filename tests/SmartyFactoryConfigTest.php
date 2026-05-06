<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Filesystem\Filesystem;
use Smarty\Smarty;
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
}
