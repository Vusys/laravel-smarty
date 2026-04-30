<?php

namespace Vusys\LaravelSmarty\Tests;

use Vusys\LaravelSmarty\SmartyEngine;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;

class SmartyEngineTest extends TestCase
{
    public function test_renders_a_template_with_assigned_variables(): void
    {
        $output = view('hello', ['name' => 'World'])->render();

        $this->assertSame("Hello, World!\n", $output);
    }

    public function test_renders_a_template_with_a_loop(): void
    {
        $output = view('loop', ['items' => ['one', 'two', 'three']])->render();

        $this->assertStringContainsString('<li>one</li>', $output);
        $this->assertStringContainsString('<li>two</li>', $output);
        $this->assertStringContainsString('<li>three</li>', $output);
    }

    public function test_smarty_takes_precedence_over_blade_when_both_files_exist(): void
    {
        $output = view('welcome', ['message' => 'hi'])->render();

        $this->assertStringStartsWith('smarty:hi', $output);
    }

    public function test_tpl_extension_is_registered_ahead_of_blade(): void
    {
        /** @var \Illuminate\View\Factory $factory */
        $factory = $this->app->make(ViewFactoryContract::class);
        $extensions = $factory->getExtensions();
        $keys = array_keys($extensions);

        $this->assertSame('smarty', $extensions['tpl']);
        $this->assertLessThan(
            array_search('blade.php', $keys, true),
            array_search('tpl', $keys, true),
            'tpl must be registered before blade.php so view() resolves Smarty templates first.'
        );
    }

    public function test_engine_resolver_returns_smarty_engine_instance(): void
    {
        $engine = $this->app['view.engine.resolver']->resolve('smarty');

        $this->assertInstanceOf(SmartyEngine::class, $engine);
    }

    public function test_view_events_fire_for_extends_parents_and_includes(): void
    {
        $events = $this->app['events'];
        $composed = [];
        $created = [];

        $events->listen('composing: *', function ($event, $params) use (&$composed) {
            $composed[] = $params[0]->getName();
        });
        $events->listen('creating: *', function ($event, $params) use (&$created) {
            $created[] = $params[0]->getName();
        });

        $output = view('page', ['msg' => 'ok'])->render();

        $this->assertStringContainsString('[layout-start]', $output);
        $this->assertStringContainsString('[nav]', $output);
        $this->assertStringContainsString('page-content:ok', $output);

        // Entry: fired by Laravel exactly once (Smarty resource skips it).
        $this->assertSame(1, array_count_values($composed)['page'] ?? 0);
        $this->assertSame(1, array_count_values($created)['page'] ?? 0);

        // Parent layout and include: fired by SmartyResource.
        $this->assertContains('layouts.main', $composed);
        $this->assertContains('partials.nav', $composed);
        $this->assertContains('layouts.main', $created);
        $this->assertContains('partials.nav', $created);
    }
}
