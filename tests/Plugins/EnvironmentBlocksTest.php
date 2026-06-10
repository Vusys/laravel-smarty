<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Smarty\Smarty;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * Orchestra testbench boots the app with APP_ENV=testing, so 'testing'
 * is the matching environment and 'production' the non-matching one
 * throughout.
 */
class EnvironmentBlocksTest extends TestCase
{
    public function test_env_block_renders_for_matching_environment(): void
    {
        $output = view('env')->render();

        $this->assertStringContainsString('[env-match]', $output);
        $this->assertStringContainsString('[env-array-match]', $output);
        $this->assertStringNotContainsString('[env-miss]', $output);
    }

    public function test_env_block_inverse_renders_for_non_matching_environment(): void
    {
        $output = view('env')->render();

        $this->assertStringContainsString('[env-inverse-match]', $output);
        $this->assertStringNotContainsString('[env-inverse-miss]', $output);
    }

    public function test_env_block_without_names_fails_closed_in_both_arms(): void
    {
        // No names is a programming mistake — neither arm should render
        // (an accidental bare {env} mustn't show its body to everyone).
        $output = view('env')->render();

        $this->assertStringNotContainsString('[env-empty]', $output);
        $this->assertStringNotContainsString('[env-empty-inverse]', $output);
    }

    public function test_production_block_and_inverse(): void
    {
        $output = view('production')->render();

        $this->assertStringNotContainsString('[prod-match]', $output);
        $this->assertStringContainsString('[prod-inverse-match]', $output);
    }

    public function test_hidden_arms_never_evaluate_their_bodies(): void
    {
        // $boom is undefined; if the hidden body were evaluated, the
        // method call on null would throw. Same lazy-body contract as
        // {auth}/{feature}.
        $output = view('env_lazy')->render();

        $this->assertStringContainsString('[after-env]', $output);
        $this->assertStringContainsString('[after-production]', $output);
    }

    public function test_blocks_are_registered_uncached(): void
    {
        // Environment is deploy state, but a page cache can outlive a
        // deploy or be shared across differently-configured nodes.
        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['env', 'production'] as $name) {
            [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_BLOCK, $name);
            $this->assertFalse($cacheable, "{{$name}} must register with cacheable=false");
        }
    }
}
