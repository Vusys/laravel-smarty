<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Debug;

use Illuminate\Filesystem\Filesystem;
use Vusys\LaravelSmarty\Debug\CompilerOverride;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * Edge-case coverage for CompilerOverride. The happy path is exercised
 * end-to-end by SourceMapTest::test_compiler_override_is_installed_and_active
 * — these tests cover idempotency, uninstall/reinstall, the install-time
 * anchor guard, and that unrelated reads pass through unchanged.
 */
class CompilerOverrideTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/laravel-smarty-override-tests';
        (new Filesystem)->ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Always clear the test seam so a failure here doesn't poison
        // other tests that rely on the real vendor path.
        CompilerOverride::setSourcePathOverrideForTesting(null);

        // Make sure the wrapper is back to its installed state for the
        // rest of the suite — we may have called uninstall() above.
        if (! CompilerOverride::isInstalled()) {
            CompilerOverride::install();
        }

        (new Filesystem)->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    public function test_install_is_idempotent(): void
    {
        $this->assertTrue(CompilerOverride::isInstalled());
        $this->assertTrue(CompilerOverride::install(), 'Second install() call should return true.');
        $this->assertTrue(CompilerOverride::isInstalled());
    }

    public function test_uninstall_then_reinstall_works(): void
    {
        CompilerOverride::uninstall();
        $this->assertFalse(CompilerOverride::isInstalled());

        $this->assertTrue(CompilerOverride::install());
        $this->assertTrue(CompilerOverride::isInstalled());

        // Wrapper is functional again — read a known file through it.
        $bytes = file_get_contents(__FILE__);
        $this->assertIsString($bytes);
        $this->assertStringContainsString('class CompilerOverrideTest', $bytes);
    }

    public function test_proxies_unrelated_file_reads_byte_for_byte(): void
    {
        // Read a file that isn't Smarty's Source.php — the wrapper
        // should hand back the on-disk bytes unchanged.
        $needle = $this->tempDir.'/payload.txt';
        $bytes = random_bytes(2048);
        file_put_contents($needle, $bytes);

        $throughWrapper = file_get_contents($needle);

        $this->assertSame($bytes, $throughWrapper);
    }

    public function test_install_fails_loud_when_anchor_missing(): void
    {
        // Synthesise a Source.php-shaped fixture without the anchor.
        $tampered = $this->tempDir.'/Source.php';
        file_put_contents($tampered, "<?php\nclass Source {\n    public function createCompiler() { return new \\Smarty\\Compiler\\Other(); }\n}\n");

        // Reinstall must run from a clean slate so install() actually
        // re-runs the anchor check rather than short-circuiting on the
        // existing self::$installed flag.
        CompilerOverride::uninstall();
        CompilerOverride::setSourcePathOverrideForTesting($tampered);

        $this->assertFalse(CompilerOverride::install(), 'install() must refuse to run when anchor is missing.');
        $this->assertFalse(CompilerOverride::isInstalled());
        $this->assertFalse(CompilerOverride::isAnchorVerified());
    }
}
