<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\View\Factory;

/**
 * `smarty.extension` was only covered at the unit level; this exercises
 * a non-default extension end-to-end through the view finder, the
 * engine resolver, and a real render.
 */
class CustomExtensionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('view.paths', [$this->viewsPath.'/custom-ext']);
        $app['config']->set('smarty.extension', 'smarty');
    }

    public function test_custom_extension_renders_through_the_resolver(): void
    {
        $output = view('greeting', ['name' => 'World'])->render();

        $this->assertSame("Hi, World!\n", $output);
    }

    public function test_custom_extension_is_registered_ahead_of_blade(): void
    {
        /** @var Factory $factory */
        $factory = $this->app->make(ViewFactoryContract::class);
        $extensions = $factory->getExtensions();
        $keys = array_keys($extensions);

        $this->assertSame('smarty', $extensions['smarty'] ?? null);
        $this->assertArrayNotHasKey('tpl', $extensions);
        $this->assertLessThan(
            array_search('blade.php', $keys, true),
            array_search('smarty', $keys, true),
        );
    }
}
