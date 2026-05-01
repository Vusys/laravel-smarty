<?php

namespace Vusys\LaravelSmarty\Tests;

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
}
