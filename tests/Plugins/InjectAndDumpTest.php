<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Symfony\Component\VarDumper\VarDumper;
use Vusys\LaravelSmarty\Tests\TestCase;

class InjectAndDumpTest extends TestCase
{
    public function test_service_function_resolves_from_container_and_assigns(): void
    {
        $this->app->bind('metrics', fn () => new class
        {
            public function name(): string
            {
                return 'pageviews';
            }
        });

        $output = view('inject')->render();

        $this->assertStringContainsString('metrics=pageviews', $output);
    }

    public function test_dump_function_routes_through_vardumper(): void
    {
        $captured = [];
        VarDumper::setHandler(function ($var) use (&$captured) {
            $captured[] = $var;
        });

        try {
            view('dump', ['payload' => ['hello' => 'world']])->render();
        } finally {
            VarDumper::setHandler(null);
        }

        $this->assertCount(1, $captured);
        $this->assertSame(['hello' => 'world'], $captured[0]);
    }

    public function test_dump_function_dumps_every_named_param(): void
    {
        $captured = [];
        VarDumper::setHandler(function ($var) use (&$captured) {
            $captured[] = $var;
        });

        try {
            view('dump_multi', ['x' => 'first', 'y' => 'second'])->render();
        } finally {
            VarDumper::setHandler(null);
        }

        $this->assertSame(['first', 'second'], $captured);
    }

    public function test_dd_function_dumps_named_params_and_halts(): void
    {
        $captured = [];
        // dd() exits at the end. Hijack the dumper so we can detect it ran,
        // then throw to interrupt before exit() reaches PHP's shutdown.
        VarDumper::setHandler(function ($var) use (&$captured) {
            $captured[] = $var;
            throw new \RuntimeException('dd-halt');
        });

        $halted = false;
        try {
            view('dd')->render();
        } catch (\Throwable) {
            $halted = true;
        } finally {
            VarDumper::setHandler(null);
        }

        $this->assertTrue($halted, '{dd} should reach the dumper and dd() should halt execution.');
        $this->assertSame(['bye'], $captured);
    }

    public function test_dump_is_a_noop_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $captured = [];
        VarDumper::setHandler(function ($var) use (&$captured) {
            $captured[] = $var;
        });

        try {
            $output = view('dump', ['payload' => ['hello' => 'world']])->render();
        } finally {
            VarDumper::setHandler(null);
        }

        $this->assertSame([], $captured, '{dump} must not reach the dumper outside local/testing.');
        $this->assertStringNotContainsString('hello', $output);
    }

    public function test_dd_is_a_noop_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $reached = false;
        VarDumper::setHandler(function () use (&$reached) {
            $reached = true;
            throw new \RuntimeException('dd-halt');
        });

        try {
            $output = view('dd')->render();
        } finally {
            VarDumper::setHandler(null);
        }

        $this->assertFalse($reached, '{dd} must not reach the dumper or halt outside local/testing.');
        $this->assertStringNotContainsString('bye', $output);
    }
}
