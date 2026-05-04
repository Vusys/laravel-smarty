<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Debug;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Vusys\LaravelSmarty\Debug\SourceMap;

/**
 * Pure-function tests against SourceMap::lookup() — no Smarty involved.
 *
 * Each test writes a small "compiled file" to a temp dir with whatever
 * combination of __SLF / __SLM markers it needs, then asserts what
 * lookup() returns. This isolates the marker parsing from the rest of
 * the source-map debug stack so a regression here fails a specific
 * test rather than the whole end-to-end suite.
 */
class SourceMapUnitTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/laravel-smarty-source-map-tests';
        (new Filesystem)->ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tempDir);
    }

    public function test_returns_null_when_compiled_file_is_missing(): void
    {
        $this->assertNull(SourceMap::lookup($this->tempDir.'/does-not-exist.tpl.php', 5));
    }

    public function test_returns_null_when_file_has_no_slf_header(): void
    {
        $path = $this->writeCompiledFile([
            '<?php',
            '/*__SLM:5*/',
            'throw new \\RuntimeException("boom");',
        ]);

        // Without an __SLF header we cannot recover the source path,
        // so lookup must surrender rather than guess.
        $this->assertNull(SourceMap::lookup($path, 3));
    }

    public function test_resolves_source_line_from_nearest_preceding_marker(): void
    {
        // Compiled file lines:
        //   1: <?php
        //   2: /*__SLF:/abs/x/y.tpl*/
        //   3: /*__SLM:3*/
        //   4: stuff
        //   5: /*__SLM:7*/
        //   6: more stuff   <- error reported here
        //   7: /*__SLM:12*/
        $path = $this->writeCompiledFile([
            '<?php',
            '/*__SLF:/abs/x/y.tpl*/',
            '/*__SLM:3*/',
            'stuff',
            '/*__SLM:7*/',
            'more stuff',
            '/*__SLM:12*/',
        ]);

        $this->assertSame(
            ['path' => '/abs/x/y.tpl', 'line' => 7],
            SourceMap::lookup($path, 6),
        );
    }

    public function test_returns_line_one_when_no_marker_precedes_error_line(): void
    {
        // The __SLM marker is AFTER the error line — lookup falls back
        // to line 1 so the user still lands on the source file even if
        // we couldn't pinpoint the statement.
        $path = $this->writeCompiledFile([
            '<?php',
            '/*__SLF:/abs/x/y.tpl*/',
            'error happens here',
            '/*__SLM:42*/',
        ]);

        $this->assertSame(
            ['path' => '/abs/x/y.tpl', 'line' => 1],
            SourceMap::lookup($path, 3),
        );
    }

    public function test_caps_search_at_error_line_so_later_markers_are_ignored(): void
    {
        $path = $this->writeCompiledFile([
            '<?php',
            '/*__SLF:/abs/x/y.tpl*/',
            '/*__SLM:5*/',
            'error happens here',
            '/*__SLM:99*/',
        ]);

        $this->assertSame(
            ['path' => '/abs/x/y.tpl', 'line' => 5],
            SourceMap::lookup($path, 4),
        );
    }

    public function test_caps_search_at_eof_when_error_line_exceeds_file_length(): void
    {
        // The file has 4 lines; an errorLine of 9999 must not index off
        // the end. The implementation clamps via min(errorLine, count).
        $path = $this->writeCompiledFile([
            '<?php',
            '/*__SLF:/abs/x/y.tpl*/',
            '/*__SLM:11*/',
            'last line',
        ]);

        $this->assertSame(
            ['path' => '/abs/x/y.tpl', 'line' => 11],
            SourceMap::lookup($path, 9999),
        );
    }

    public function test_picks_rightmost_marker_when_line_packs_multiple(): void
    {
        // Smarty packs adjacent compiled tags onto one physical line of
        // the .tpl.php. The rightmost marker on that line is the one
        // immediately preceding the error within the same compiled
        // line.
        $path = $this->writeCompiledFile([
            '<?php',
            '/*__SLF:/abs/x/y.tpl*/',
            '/*__SLM:5*/ A; /*__SLM:6*/ B; /*__SLM:7*/ C;',
            'error happens here',
        ]);

        $this->assertSame(
            ['path' => '/abs/x/y.tpl', 'line' => 7],
            SourceMap::lookup($path, 4),
        );
    }

    public function test_uses_first_slf_header_when_multiple_present(): void
    {
        // Smarty's inheritance can produce a single compiled file
        // containing pieces of both parent and child templates, each
        // emitted with their own /*__SLF*/ header. The first hit wins
        // — that's what the implementation does today via `break`.
        $path = $this->writeCompiledFile([
            '<?php',
            '/*__SLF:/abs/parent.tpl*/',
            '/*__SLF:/abs/child.tpl*/',
            '/*__SLM:4*/',
            'error here',
        ]);

        $mapped = SourceMap::lookup($path, 5);

        $this->assertNotNull($mapped);
        $this->assertSame('/abs/parent.tpl', $mapped['path']);
    }

    /**
     * @param  list<string>  $lines
     */
    private function writeCompiledFile(array $lines): string
    {
        $path = $this->tempDir.'/'.uniqid('compiled_', true).'.tpl.php';
        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }
}
