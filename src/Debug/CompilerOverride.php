<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Debug;

/**
 * Stream wrapper that rewrites Smarty's Template/Source.php on autoload
 * to swap the hard-coded `\Smarty\Compiler\Template` constructor in
 * Source::createCompiler() for our LineTrackingCompiler subclass.
 *
 * Smarty's Source class instantiates the compiler directly with no
 * extension point, so without this rewrite there is no way to install
 * a custom compiler. The same technique is used by dg/bypass-finals to
 * strip `final` keywords from Symfony classes for testing — we
 * register ourselves as the file:// stream wrapper, transparently
 * proxy every read except the one Source.php load, and patch that one
 * via a single str_replace before PHP compiles it.
 *
 * The replacement is a single-line anchor; if a future Smarty release
 * changes the call site we detect the absence at install time and
 * leave Source.php untouched. install() returns false in that case so
 * callers can surface the failure rather than silently shipping
 * unannotated compiles.
 */
class CompilerOverride
{
    public const ANCHOR = 'return new \\Smarty\\Compiler\\Template($this->smarty);';

    public const REPLACEMENT = 'return new \\Vusys\\LaravelSmarty\\Debug\\LineTrackingCompiler($this->smarty);';

    private const TARGET_SUFFIX = '/smarty/smarty/src/Template/Source.php';

    private static bool $installed = false;

    private static bool $anchorVerified = false;

    /**
     * Test-only override for the path lookup. When non-null, install()
     * uses this path instead of locating Smarty via the vendor walk.
     * Lets tests point install() at a synthesised Source.php missing
     * the anchor and verify the install-time guard fires.
     */
    private static ?string $sourcePathOverride = null;

    /** @var resource|null */
    public $context;

    /** @var resource|null */
    private $handle;

    public static function install(): bool
    {
        if (self::$installed) {
            return true;
        }

        $sourcePath = self::vendorSourcePath();
        if ($sourcePath === null) {
            return false;
        }

        // Verify the replacement anchor exists in the on-disk Source.php
        // before installing the wrapper. If it doesn't, a Smarty update
        // has shifted the call site — fail loud rather than silently
        // shipping unannotated compiles.
        $original = @file_get_contents($sourcePath);
        if ($original === false || ! str_contains($original, self::ANCHOR)) {
            return false;
        }

        self::$anchorVerified = true;

        if (in_array('file', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('file');
        }

        if (! stream_wrapper_register('file', self::class)) {
            stream_wrapper_restore('file');

            return false;
        }

        self::$installed = true;

        // Bust any cached Source.php bytecode so opcache picks up the
        // rewritten version on the next include.
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($sourcePath, true);
        }

        return true;
    }

    /**
     * For tests: detach the wrapper so the next install() can re-run.
     */
    public static function uninstall(): void
    {
        if (! self::$installed) {
            return;
        }

        stream_wrapper_restore('file');
        self::$installed = false;
        self::$anchorVerified = false;
    }

    public static function isInstalled(): bool
    {
        return self::$installed;
    }

    public static function isAnchorVerified(): bool
    {
        return self::$anchorVerified;
    }

    /**
     * Test seam: redirect install()'s anchor probe to a custom file.
     * Pass null to clear. Production code never calls this.
     */
    public static function setSourcePathOverrideForTesting(?string $path): void
    {
        self::$sourcePathOverride = $path;
    }

    private static function vendorSourcePath(): ?string
    {
        if (self::$sourcePathOverride !== null) {
            return self::$sourcePathOverride;
        }

        // Walk up from this file to find /vendor/smarty/smarty/src/Template/Source.php.
        // Works regardless of whether the package is installed as a vendor
        // dependency or developed in-place.
        $candidates = [
            __DIR__.'/../../vendor/smarty/smarty/src/Template/Source.php',
            __DIR__.'/../../../../smarty/smarty/src/Template/Source.php',
        ];

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_file($real)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    private function withNativeFile(callable $fn)
    {
        stream_wrapper_restore('file');
        try {
            return $fn();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $usePath = (bool) ($options & STREAM_USE_PATH);
        $report = (bool) ($options & STREAM_REPORT_ERRORS);

        return (bool) $this->withNativeFile(function () use ($path, $mode, $usePath, $report) {
            $handle = $report
                ? fopen($path, $mode, $usePath)
                : @fopen($path, $mode, $usePath);

            if ($handle === false) {
                return false;
            }

            if (str_ends_with(strtr($path, '\\', '/'), self::TARGET_SUFFIX) && str_contains($mode, 'r')) {
                $contents = stream_get_contents($handle);
                fclose($handle);

                $rewritten = is_string($contents)
                    ? str_replace(self::ANCHOR, self::REPLACEMENT, $contents)
                    : '';

                $temp = fopen('php://temp', 'r+');
                if ($temp === false) {
                    return false;
                }
                fwrite($temp, $rewritten);
                rewind($temp);
                $this->handle = $temp;
            } else {
                $this->handle = $handle;
            }

            return true;
        });
    }

    /** @return string|false */
    public function stream_read(int $count)
    {
        if (! is_resource($this->handle) || $count < 1) {
            return false;
        }

        return fread($this->handle, $count);
    }

    public function stream_eof(): bool
    {
        return is_resource($this->handle) ? feof($this->handle) : true;
    }

    /** @return int|false */
    public function stream_tell()
    {
        return is_resource($this->handle) ? ftell($this->handle) : false;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return is_resource($this->handle) && fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /** @return array<int|string, int>|false */
    public function stream_stat()
    {
        return is_resource($this->handle) ? fstat($this->handle) : false;
    }

    public function stream_flush(): bool
    {
        return is_resource($this->handle) && fflush($this->handle);
    }

    public function stream_lock(int $operation): bool
    {
        if (! is_resource($this->handle)) {
            return false;
        }

        // PHP's stream layer occasionally calls stream_lock with a 0
        // operation (no LOCK_SH/LOCK_EX/LOCK_UN bits) — flock() emits a
        // warning if we forward that, so treat it as a successful no-op.
        $masked = $operation & (LOCK_SH | LOCK_EX | LOCK_UN);
        if ($masked === 0) {
            return true;
        }

        // flock's signature in phpstan is int<0,7>; an unrecognised
        // higher bit (e.g. LOCK_NB on its own) gets clamped here.
        return flock($this->handle, $operation & 7);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    public function stream_write(string $data): int
    {
        return is_resource($this->handle) ? (fwrite($this->handle, $data) ?: 0) : 0;
    }

    public function stream_truncate(int $newSize): bool
    {
        return $newSize >= 0 && is_resource($this->handle) && ftruncate($this->handle, $newSize);
    }

    /** @return array<int|string, int>|false */
    public function url_stat(string $path, int $flags)
    {
        /** @var array<int|string, int>|false $stat */
        $stat = $this->withNativeFile(fn () => ($flags & STREAM_URL_STAT_QUIET) === STREAM_URL_STAT_QUIET ? @stat($path) : stat($path));

        return $stat;
    }

    public function unlink(string $path): bool
    {
        return (bool) $this->withNativeFile(fn () => @unlink($path));
    }

    public function rename(string $from, string $to): bool
    {
        return (bool) $this->withNativeFile(fn () => @rename($from, $to));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return (bool) $this->withNativeFile(fn () => @mkdir(
            $path,
            $mode,
            ($options & STREAM_MKDIR_RECURSIVE) === STREAM_MKDIR_RECURSIVE,
        ));
    }

    public function rmdir(string $path, int $options): bool
    {
        return (bool) $this->withNativeFile(fn () => @rmdir($path));
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return (bool) $this->withNativeFile(function () use ($path) {
            $handle = @opendir($path);
            if ($handle === false) {
                return false;
            }
            $this->handle = $handle;

            return true;
        });
    }

    /** @return string|false */
    public function dir_readdir()
    {
        return is_resource($this->handle) ? readdir($this->handle) : false;
    }

    public function dir_rewinddir(): bool
    {
        if (! is_resource($this->handle)) {
            return false;
        }
        rewinddir($this->handle);

        return true;
    }

    public function dir_closedir(): void
    {
        if (is_resource($this->handle)) {
            closedir($this->handle);
        }
    }

    /**
     * @param  mixed  $value
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {
        return (bool) $this->withNativeFile(function () use ($path, $option, $value) {
            switch ($option) {
                case STREAM_META_TOUCH:
                    $args = array_values(array_filter(
                        is_array($value) ? $value : [],
                        is_int(...),
                    ));

                    return @touch($path, ...$args);
                case STREAM_META_OWNER:
                case STREAM_META_OWNER_NAME:
                    return (is_int($value) || is_string($value)) && @chown($path, $value);
                case STREAM_META_GROUP:
                case STREAM_META_GROUP_NAME:
                    return (is_int($value) || is_string($value)) && @chgrp($path, $value);
                case STREAM_META_ACCESS:
                    return is_int($value) && @chmod($path, $value);
            }

            return false;
        });
    }
}
