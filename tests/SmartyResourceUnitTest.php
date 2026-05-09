<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Engine;
use Illuminate\View\Factory;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use ReflectionMethod;
use Smarty\Smarty;
use Vusys\LaravelSmarty\SmartyResource;

/**
 * Pure-logic unit tests for SmartyResource — exercises the path-handling
 * branches without booting Laravel's render pipeline. Uses a real Smarty
 * instance so we don't have to stub Template (whose destructor needs a
 * real Smarty parent and would otherwise warn during teardown).
 */
class SmartyResourceUnitTest extends PHPUnitTestCase
{
    public function test_fire_for_template_short_circuits_when_source_path_is_null(): void
    {
        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->never())->method('dispatch');

        $resource = new SmartyResource(
            $this->createStub(Factory::class),
            $events,
            $this->createStub(Engine::class),
            'tpl',
        );

        // string: resource has no filepath, so SmartyResource must skip it
        // — view-creating events for a synthetic template are useless.
        $smarty = new Smarty;
        $template = $smarty->createTemplate('string:hello world');

        $resource->fireForTemplate($template);
    }

    public function test_derive_view_name_falls_back_to_basename_when_path_outside_template_dirs(): void
    {
        $resource = new SmartyResource(
            $this->createStub(Factory::class),
            $this->createStub(Dispatcher::class),
            $this->createStub(Engine::class),
            'tpl',
        );

        $smarty = new Smarty;
        $smarty->setTemplateDir(['/abs/views']);

        $name = $this->invokeDeriveViewName(
            $resource,
            $smarty,
            '/somewhere/else/standalone.tpl',
        );

        // No template-dir prefix matches, so derivation falls back to
        // basename + extension stripping.
        $this->assertSame('standalone', $name);
    }

    public function test_derive_view_name_strips_template_dir_prefix(): void
    {
        $resource = new SmartyResource(
            $this->createStub(Factory::class),
            $this->createStub(Dispatcher::class),
            $this->createStub(Engine::class),
            'tpl',
        );

        $smarty = new Smarty;
        $smarty->setTemplateDir(['/abs/views']);

        $name = $this->invokeDeriveViewName(
            $resource,
            $smarty,
            '/abs/views/admin/users.tpl',
        );

        $this->assertSame('admin.users', $name);
    }

    private function invokeDeriveViewName(SmartyResource $resource, Smarty $smarty, string $path): string
    {
        $method = new ReflectionMethod($resource, 'deriveViewName');

        return (string) $method->invoke($resource, $smarty, $path);
    }
}
