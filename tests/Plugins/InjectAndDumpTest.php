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
}
