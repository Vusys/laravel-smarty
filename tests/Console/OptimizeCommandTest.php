<?php

namespace Vusys\LaravelSmarty\Tests\Console;

use Illuminate\Filesystem\Filesystem;
use Vusys\LaravelSmarty\Tests\TestCase;

class OptimizeCommandTest extends TestCase
{
    public function test_compiles_all_templates(): void
    {
        $this->artisan('smarty:optimize')->assertSuccessful();

        $this->assertNotEmpty(
            (new Filesystem)->allFiles($this->compilePath),
            'Compile dir should be populated after smarty:optimize.',
        );
    }

    public function test_force_flag_recompiles(): void
    {
        $this->artisan('smarty:optimize')->assertSuccessful();
        $this->artisan('smarty:optimize', ['--force' => true])->assertSuccessful();
    }

    public function test_extension_option_is_honoured(): void
    {
        $this->artisan('smarty:optimize', ['--extension' => 'tpl'])->assertSuccessful();

        $files = new Filesystem;
        $this->assertNotEmpty($files->allFiles($this->compilePath));
    }

    public function test_extension_option_overrides_config_default(): void
    {
        // Config default points at an extension that doesn't match the
        // fixtures; --extension=tpl must still win. A Coalesce swap on
        // the ?? would prefer config (zero matches) over the option.
        config()->set('smarty.extension', 'zzz');

        $this->artisan('smarty:optimize', ['--extension' => 'tpl'])
            ->expectsOutputToContain('Compiled')
            ->assertSuccessful();

        $this->assertNotEmpty((new Filesystem)->allFiles($this->compilePath));
    }

    public function test_unknown_extension_compiles_nothing(): void
    {
        // No fixtures end in `.absent`; the original ternary keeps the
        // provided extension as-is, so 0 files compile. A Ternary swap
        // would fall back to 'tpl' and compile the real fixtures —
        // catching the swap.
        $this->artisan('smarty:optimize', ['--extension' => 'absent'])
            ->expectsOutputToContain('Compiled 0 template(s).')
            ->assertSuccessful();

        $this->assertEmpty((new Filesystem)->allFiles($this->compilePath));
    }

    public function test_progress_trail_is_streamed_to_console(): void
    {
        // Smarty echoes "<dir>---<file> compiled in <s> seconds" per
        // file. Asserting that text confirms both the `$trail !== ''`
        // guard and the `$this->line(...)` call survive.
        $this->artisan('smarty:optimize')
            ->expectsOutputToContain('compiled in')
            ->assertSuccessful();
    }
}
