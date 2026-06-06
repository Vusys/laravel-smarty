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

    public function test_fire_for_template_short_circuits_when_source_itself_is_null(): void
    {
        // Template::getSource() is documented as ?Source — a null source
        // would have us pass through to a method call on null without the
        // null-safe operator. Test that path explicitly.
        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->never())->method('dispatch');

        $resource = new SmartyResource(
            $this->createStub(Factory::class),
            $events,
            $this->createStub(Engine::class),
            'tpl',
        );

        $smarty = new Smarty;
        $template = $smarty->createTemplate('string:hello world');
        $template->setSource(null);

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

    public function test_derive_view_name_normalises_backslashes_in_the_source_path(): void
    {
        // Windows paths arrive with '\\'. Without the normalize on the
        // path side, the template-dir prefix check (which always works
        // with '/') would fail to recognise its own directory and we'd
        // fall through to basename(), losing the subdir structure.
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
            '/abs/views\\admin\\users.tpl',
        );

        $this->assertSame('admin.users', $name);
    }

    public function test_derive_view_name_normalises_backslashes_in_the_template_dir(): void
    {
        // Same normalize on the prefix side: a template-dir configured
        // with backslashes (Windows-style) must still match a forward-
        // slash path. Without the prefix-side str_replace, the prefix
        // wouldn't appear in the normalised path and the prefix check
        // would miss.
        $resource = new SmartyResource(
            $this->createStub(Factory::class),
            $this->createStub(Dispatcher::class),
            $this->createStub(Engine::class),
            'tpl',
        );

        $smarty = new Smarty;
        $smarty->setTemplateDir(['C:\\abs\\views']);

        $name = $this->invokeDeriveViewName(
            $resource,
            $smarty,
            'C:/abs/views/admin/users.tpl',
        );

        $this->assertSame('admin.users', $name);
    }

    public function test_logical_name_treats_regex_metacharacters_in_extension_literally(): void
    {
        // smarty.extension is user-configurable; if it ever contains
        // regex metachars (here, a literal '.'), the strip pattern must
        // treat them literally. Without preg_quote, '.' would match any
        // char and the strip would fire on filenames that don't actually
        // share the configured suffix.
        $resource = new SmartyResource(
            $this->createStub(Factory::class),
            $this->createStub(Dispatcher::class),
            $this->createStub(Engine::class),
            'a.b',
        );

        $smarty = new Smarty;
        $smarty->setTemplateDir(['/abs/views']);

        $name = $this->invokeDeriveViewName(
            $resource,
            $smarty,
            '/abs/views/admin/users.a-b',
        );

        // 'users.a-b' does NOT carry the configured '.a.b' suffix, so
        // the strip must not fire. Without preg_quote, '\.a.b' would
        // wildcard-match '.a-b' and we'd lose the '.a-b' suffix from
        // the logical name.
        $this->assertSame('admin.users.a-b', $name);
    }

    private function invokeDeriveViewName(SmartyResource $resource, Smarty $smarty, string $path): string
    {
        $method = new ReflectionMethod($resource, 'deriveViewName');

        return (string) $method->invoke($resource, $smarty, $path);
    }
}
