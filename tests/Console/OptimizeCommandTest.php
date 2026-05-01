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
}
