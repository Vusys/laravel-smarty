<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Security;

use Illuminate\View\ViewException;
use Vusys\LaravelSmarty\Security\BalancedSecurityPolicy;
use Vusys\LaravelSmarty\Tests\TestCase;

class BalancedSecurityPolicyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smarty.security', 'balanced');
    }

    public function test_engine_has_balanced_policy_attached(): void
    {
        // Touch the view system so the engine is built lazily.
        view('security_ok', ['name' => 'world'])->render();

        $engine = $this->app['view']->getEngineResolver()->resolve('smarty');

        $this->assertInstanceOf(
            BalancedSecurityPolicy::class,
            $engine->smarty()->security_policy,
        );
    }

    public function test_php_tag_is_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/php.*not allowed|disabled/i');

        view('security_php')->render();
    }

    public function test_math_tag_is_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/math.*not allowed|disabled/i');

        view('security_math')->render();
    }

    public function test_super_globals_are_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/super globals/i');

        view('security_super_global')->render();
    }

    public function test_static_class_access_is_blocked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/static class.*not allowed/i');

        view('security_static_class')->render();
    }

    public function test_constants_are_still_allowed(): void
    {
        $output = view('security_constant')->render();

        $this->assertStringContainsString((string) PHP_INT_MAX, $output);
    }

    public function test_normal_templates_still_render(): void
    {
        $output = view('security_ok', ['name' => 'world'])->render();

        $this->assertStringContainsString('Hello, WORLD!', $output);
    }
}
