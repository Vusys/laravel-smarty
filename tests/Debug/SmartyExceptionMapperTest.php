<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Debug;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper;
use Illuminate\View\ViewException;
use RuntimeException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Vusys\LaravelSmarty\Debug\SmartyExceptionMapper;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * Decoupled tests for the trace-rewrite logic. We stand up a synthetic
 * compiled file with __SLF / __SLM markers and throw from inside it,
 * so the resulting throwable has a .tpl.php frame for the mapper to
 * remap — without needing a live Smarty compile.
 *
 * The mapper extends Laravel's BladeMapper, so we resolve it from the
 * container (the service provider rebinds BladeMapper::class to our
 * subclass) to also exercise that wiring.
 */
class SmartyExceptionMapperTest extends TestCase
{
    private string $tempDir;

    private string $sourcePath;

    private string $compiledPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel 10 does not ship the BladeMapper exception-page
        // helper (introduced in Laravel 11), so neither the subclass
        // nor the container binding exists there.
        if (! class_exists(BladeMapper::class)) {
            $this->markTestSkipped('BladeMapper requires Laravel 11+.');
        }

        $this->tempDir = sys_get_temp_dir().'/laravel-smarty-mapper-tests';
        (new Filesystem)->ensureDirectoryExists($this->tempDir);
        $this->sourcePath = $this->tempDir.'/fake.tpl';
        $this->compiledPath = $this->tempDir.'/fake.tpl.php';
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    public function test_resolves_to_blade_mapper_subclass_via_container(): void
    {
        $mapper = $this->app->make(BladeMapper::class);

        $this->assertInstanceOf(SmartyExceptionMapper::class, $mapper);
        $this->assertInstanceOf(BladeMapper::class, $mapper);
    }

    public function test_rewrites_tpl_php_frame_to_source_path_and_line(): void
    {
        $exception = $this->throwFromFakeCompiledFile(markerLine: 42);

        $flat = FlattenException::createFromThrowable($exception);
        $mapped = $this->mapper()->map($flat);

        // After mapping, the frame whose file WAS the compiled file
        // now reports the source .tpl path. Look for it by the
        // post-rewrite file value.
        $frame = $this->findFrameByExactFile($mapped->getTrace(), $this->sourcePath);
        $this->assertNotNull($frame, 'Mapper should rewrite the compiled-file frame to the source path.');
        $this->assertSame(42, $frame['line']);
    }

    public function test_unwrap_view_exception_then_remap_tpl_frame(): void
    {
        $inner = $this->throwFromFakeCompiledFile(markerLine: 7);
        $wrapped = new ViewException($inner->getMessage(), 0, 1, $inner->getFile(), $inner->getLine(), $inner);

        $flat = FlattenException::createFromThrowable($wrapped);
        $mapped = $this->mapper()->map($flat);

        // BladeMapper's map() unwraps ViewException via getPrevious(),
        // so after mapping the result represents the inner throwable.
        $this->assertNotSame(ViewException::class, $mapped->getClass());

        $frame = $this->findFrameByExactFile($mapped->getTrace(), $this->sourcePath);
        $this->assertNotNull($frame);
        $this->assertSame(7, $frame['line']);
    }

    public function test_passes_through_frames_that_are_not_tpl_php(): void
    {
        // Throw from a regular .php fixture (no markers, not a .tpl.php).
        // The frame should come back unchanged.
        $fn = 'regular_boom_'.bin2hex(random_bytes(4));
        $regularFile = $this->tempDir.'/regular_'.$fn.'.php';
        file_put_contents($regularFile, "<?php\nfunction {$fn}() { throw new \\RuntimeException('boom'); }\n");
        require $regularFile;

        try {
            $fn();
            $this->fail('expected throw');
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $flat = FlattenException::createFromThrowable($thrown);
        $mapped = $this->mapper()->map($flat);

        $frame = $this->findFrameInFile($mapped->getTrace(), $regularFile);
        $this->assertNotNull($frame);
        $this->assertSame(realpath($regularFile), realpath((string) $frame['file']));
    }

    public function test_leaves_frames_alone_when_file_or_line_keys_are_missing_or_wrong_type(): void
    {
        // Internal/native callable frames in PHP's stack traces sometimes
        // omit `file` or `line` keys — e.g. frames from array_map's callback
        // dispatch. The mapper must skip those frames defensively rather
        // than choking on missing keys or wrong types.
        $exception = $this->throwFromFakeCompiledFile(markerLine: 5);

        $flat = FlattenException::createFromThrowable($exception);

        // Inject a couple of malformed frames at the front of the trace.
        $original = $flat->getTrace();
        $malformed = [
            ['function' => 'native_call'],                         // no file/line
            ['file' => $this->compiledPath, 'line' => '7'],         // line is a string, not int
            ['file' => 12345, 'line' => 7],                         // file is not a string
        ];
        (function () use ($malformed, $original) {
            $this->trace = array_merge($malformed, $original);
        })->call($flat);

        // Must not throw and must still rewrite the legitimate frame.
        $mapped = $this->mapper()->map($flat);

        $frame = $this->findFrameByExactFile($mapped->getTrace(), $this->sourcePath);
        $this->assertNotNull($frame);
        $this->assertSame(5, $frame['line']);
    }

    public function test_leaves_trace_unchanged_when_compiled_file_has_no_markers(): void
    {
        // Same shape as the rewrite test, but the fake compiled file
        // has no __SLF/__SLM markers — simulates an old compile from
        // before the package upgrade. The mapper must preserve the
        // original frame rather than fabricate a mapping.
        $fn = 'unmarked_boom_'.bin2hex(random_bytes(4));
        file_put_contents($this->compiledPath, "<?php\nfunction {$fn}() { throw new \\RuntimeException('boom'); }\n");
        require $this->compiledPath;

        try {
            $fn();
            $this->fail('expected throw');
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $flat = FlattenException::createFromThrowable($thrown);
        $mapped = $this->mapper()->map($flat);

        $frame = $this->findFrameInFile($mapped->getTrace(), $this->compiledPath);
        $this->assertNotNull($frame);
        $this->assertSame(realpath($this->compiledPath), realpath((string) $frame['file']));
    }

    private function mapper(): SmartyExceptionMapper
    {
        $mapper = $this->app->make(BladeMapper::class);
        $this->assertInstanceOf(SmartyExceptionMapper::class, $mapper);

        return $mapper;
    }

    private function throwFromFakeCompiledFile(int $markerLine): RuntimeException
    {
        // Functions need unique names per test or the require below
        // will hit a duplicate-class error across the test run.
        $fn = 'fake_boom_'.bin2hex(random_bytes(4));

        // Pad with newlines so the throw lands on a line *after* the
        // marker; lookup() walks BACKWARDS from the throw line to find
        // the most recent preceding marker. The compiled file is
        // structurally similar to what LineTrackingCompiler produces.
        file_put_contents($this->compiledPath, <<<PHP
            <?php
            /*__SLF:{$this->sourcePath}*/
            /*__SLM:{$markerLine}*/
            function {$fn}() {
                throw new \\RuntimeException('boom');
            }
            PHP);

        require $this->compiledPath;

        try {
            $fn();
        } catch (RuntimeException $e) {
            return $e;
        }

        $this->fail('expected throw');
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     * @return array<string, mixed>|null
     */
    private function findFrameInFile(array $trace, string $needle): ?array
    {
        // PHP resolves /var/folders/... to /private/var/folders/... on
        // macOS when including files, so trace frames carry the
        // resolved path. Compare via realpath on both sides.
        $needleReal = realpath($needle) ?: $needle;

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            if (! is_string($file)) {
                continue;
            }
            $fileReal = realpath($file) ?: $file;
            if ($fileReal === $needleReal) {
                return $frame;
            }
        }

        return null;
    }

    /**
     * After the mapper rewrites a frame, its file becomes the literal
     * value embedded in the __SLF marker — which may not exist on disk
     * (the .tpl source we synthesised), so realpath() can't be used.
     *
     * @param  list<array<string, mixed>>  $trace
     * @return array<string, mixed>|null
     */
    private function findFrameByExactFile(array $trace, string $needle): ?array
    {
        foreach ($trace as $frame) {
            if (($frame['file'] ?? null) === $needle) {
                return $frame;
            }
        }

        return null;
    }
}
