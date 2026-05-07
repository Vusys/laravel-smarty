<?php

namespace Vusys\LaravelSmarty\Tests\Console;

use Illuminate\Filesystem\Filesystem;
use Vusys\LaravelSmarty\Tests\TestCase;

class ClearCompiledCommandTest extends TestCase
{
    public function test_clears_compiled_templates(): void
    {
        view('hello', ['name' => 'World'])->render();

        $files = new Filesystem;
        $this->assertNotEmpty($files->files($this->compilePath));

        $this->artisan('smarty:clear-compiled')->assertSuccessful();

        $this->assertEmpty($files->files($this->compilePath));
    }

    public function test_file_option_targets_a_single_template(): void
    {
        view('hello', ['name' => 'World'])->render();
        view('loop', ['items' => ['one']])->render();

        $files = new Filesystem;
        $this->assertCount(2, $files->files($this->compilePath));

        $this->artisan('smarty:clear-compiled', ['--file' => 'hello.tpl'])->assertSuccessful();

        // --file= must scope the clear to one template; the other compiled
        // output stays. Without the option pass-through this falls back to
        // clearAll() and both files vanish.
        $this->assertCount(1, $files->files($this->compilePath));
    }
}
