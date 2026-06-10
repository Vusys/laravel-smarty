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

    public function test_empty_file_option_still_clears_everything(): void
    {
        view('hello', ['name' => 'World'])->render();
        view('loop', ['items' => ['one']])->render();

        $files = new Filesystem;
        $this->assertCount(2, $files->files($this->compilePath));

        // Symfony parses `--file=` as an empty string. Aligned with
        // smarty:clear-cache: an empty value falls through to a full
        // clear rather than silently clearing nothing.
        $this->artisan('smarty:clear-compiled', ['--file' => ''])->assertSuccessful();

        $this->assertEmpty($files->files($this->compilePath));
    }

    public function test_expire_option_preserves_fresh_compiled_files(): void
    {
        view('hello', ['name' => 'World'])->render();

        $files = new Filesystem;
        $this->assertCount(1, $files->files($this->compilePath));

        // Fresh compile, 9999s expiry — must spare the file. The ternary
        // mutant that swaps to `null` would force a clear-all and remove
        // it. The success line confirms 0 files were removed.
        $this->artisan('smarty:clear-compiled', ['--expire' => '9999'])
            ->expectsOutputToContain('Removed 0 compiled Smarty file(s).')
            ->assertSuccessful();

        $this->assertCount(1, $files->files($this->compilePath));
    }

    public function test_compile_id_option_is_passed_through(): void
    {
        view('hello', ['name' => 'World'])->render();
        view('loop', ['items' => ['one']])->render();

        $files = new Filesystem;
        $this->assertCount(2, $files->files($this->compilePath));

        // No compiled file matches an unknown compile_id, so this is a
        // no-op. A Ternary mutant that drops the compileId (passing null)
        // would clear both files — the count assertion catches it.
        $this->artisan('smarty:clear-compiled', ['--compile-id' => 'nope'])
            ->expectsOutputToContain('Removed 0 compiled Smarty file(s).')
            ->assertSuccessful();

        $this->assertCount(2, $files->files($this->compilePath));
    }
}
