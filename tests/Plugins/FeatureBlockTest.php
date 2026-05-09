<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Plugins\FeaturePlugins;
use Vusys\LaravelSmarty\SmartyServiceProvider;
use Vusys\LaravelSmarty\Tests\TestCase;

class FeatureBlockTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Feature::class)) {
            $this->markTestSkipped('laravel/pennant is not installed.');
        }

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [SmartyServiceProvider::class, PennantServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Pennant's default driver is `database`, which would try to hit a
        // `features` table that doesn't exist in this in-memory test setup.
        // Force the array driver so flag definitions resolve in-process.
        $app['config']->set('pennant.default', 'array');
    }

    public function test_feature_block_renders_when_flag_is_active(): void
    {
        Feature::define('new-dashboard', static fn () => true);
        Feature::define('off-flag', static fn () => false);

        $output = view('feature')->render();

        $this->assertStringContainsString('[on]', $output);
        $this->assertStringNotContainsString('[off]', $output);
    }

    public function test_feature_block_does_not_evaluate_body_when_inactive(): void
    {
        Feature::define('off-flag', static fn () => false);

        $output = view('feature_lazy')->render();

        $this->assertStringContainsString('G=skipped', $output);
        $this->assertStringNotContainsString('F=', $output);
    }

    public function test_feature_block_scopes_check_when_for_is_passed(): void
    {
        // Pennant accepts any value as a scope; strings serialize cleanly
        // through the array driver, which is enough to prove the `for=`
        // param flows from Smarty through to PendingScopedFeatureInteraction.
        Feature::define('beta-export', static fn (string $scope) => $scope === 'allowed-user');

        $allowedOutput = view('feature_for', ['user' => 'allowed-user'])->render();
        $deniedOutput = view('feature_for', ['user' => 'denied-user'])->render();

        $this->assertStringContainsString('[for-yes]', $allowedOutput);
        $this->assertStringNotContainsString('[for-yes]', $deniedOutput);
    }

    public function test_register_is_a_no_op_when_pennant_is_not_installed(): void
    {
        $smarty = new Smarty;

        FeaturePluginsWithoutPennant::register($smarty);

        $this->assertNull(
            $smarty->getRegisteredPlugin(Smarty::PLUGIN_BLOCK, 'feature'),
            'register() should leave the {feature} tag unregistered when Pennant is unavailable',
        );
    }

    public function test_register_installs_block_plugin_when_pennant_is_installed(): void
    {
        $smarty = new Smarty;

        FeaturePlugins::register($smarty);

        $this->assertNotNull(
            $smarty->getRegisteredPlugin(Smarty::PLUGIN_BLOCK, 'feature'),
            'register() should install the {feature} block when Pennant is available',
        );
    }
}

/**
 * Test seam: forces the Pennant-availability guard to false so the
 * early-return branch in FeaturePlugins::register() is exercised even
 * though laravel/pennant *is* installed in the dev environment.
 */
final class FeaturePluginsWithoutPennant extends FeaturePlugins
{
    protected static function pennantInstalled(): bool
    {
        return false;
    }
}
