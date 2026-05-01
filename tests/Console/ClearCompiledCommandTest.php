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
}
