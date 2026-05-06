<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Filesystem\Filesystem;

/**
 * Pin that a `resources/views/vendor/pagination/<preset>.tpl` published into
 * one of the configured view paths takes precedence over both the package's
 * bundled `.tpl` and Laravel's framework Blade pagination view, exercising
 * the per-view-path scan in SmartyServiceProvider::registerPaginationViews.
 */
class PaginationVendorOverrideTest extends TestCase
{
    protected string $vendorDir;

    protected function setUp(): void
    {
        $this->vendorDir = sys_get_temp_dir().'/laravel-smarty-tests/vendor-pagination-'.bin2hex(random_bytes(4));

        $files = new Filesystem;
        $files->ensureDirectoryExists($this->vendorDir.'/vendor/pagination');
        $files->put(
            $this->vendorDir.'/vendor/pagination/bootstrap-5.tpl',
            "OVERRIDDEN BOOTSTRAP-5\n",
        );

        parent::setUp();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->vendorDir);

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Service provider's callAfterResolving('view', ...) reads view.paths
        // at boot and prepends any */vendor/pagination dir that exists. Drop
        // ours into the list so its precedence is exercised.
        $paths = (array) $app['config']->get('view.paths', []);
        array_unshift($paths, $this->vendorDir);
        $app['config']->set('view.paths', $paths);
    }

    public function test_vendor_published_override_wins_over_bundled_tpl(): void
    {
        $factory = $this->app->make(ViewFactoryContract::class);
        $path = $factory->getFinder()->find('pagination::bootstrap-5');

        $this->assertSame($this->vendorDir.'/vendor/pagination/bootstrap-5.tpl', $path);

        $rendered = $factory->make('pagination::bootstrap-5')->render();
        $this->assertStringContainsString('OVERRIDDEN BOOTSTRAP-5', $rendered);
    }
}
