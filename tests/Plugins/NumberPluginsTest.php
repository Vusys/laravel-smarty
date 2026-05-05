<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Number;
use Vusys\LaravelSmarty\Tests\TestCase;

class NumberPluginsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Number::class)) {
            $this->markTestSkipped('Illuminate\\Support\\Number requires Laravel 11+.');
        }
    }

    public function test_modifiers_format_via_laravel_number(): void
    {
        $output = view('numbers')->render();

        $this->assertStringContainsString('currency_default='.Number::currency(1234.56), $output);
        $this->assertStringContainsString('currency_gbp='.Number::currency(1234.56, 'GBP'), $output);
        $this->assertStringContainsString('file_size_kb='.Number::fileSize(1536), $output);
        $this->assertStringContainsString('file_size_mb='.Number::fileSize(4500000, 1), $output);
        $this->assertStringContainsString('percentage='.Number::percentage(12), $output);
        $this->assertStringContainsString('percentage_precise='.Number::percentage(12.345, 1), $output);
        $this->assertStringContainsString('abbreviate='.Number::abbreviate(1500), $output);
        $this->assertStringContainsString('abbreviate_precise='.Number::abbreviate(1500000, 1), $output);
        $this->assertStringContainsString('for_humans='.Number::forHumans(1500), $output);
    }
}
