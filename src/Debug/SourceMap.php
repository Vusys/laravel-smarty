<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Debug;

/**
 * Heuristic source-map between Smarty's compiled PHP output and the
 * original .tpl source.
 *
 * Smarty's compiler does not emit any source-line annotations into the
 * compiled file, so a runtime error inside template body code (e.g. a
 * method call on null) surfaces a stack trace pointing at the compiled
 * file under storage/framework/smarty/compile/<hash>_<file>.tpl.php
 * with no easy way for a developer to locate the offending line in the
 * .tpl source.
 *
 * This class injects two kinds of markers into the compiled output via
 * a Smarty postfilter:
 *
 *   /*__SLF:/abs/path/to/source.tpl*\/   - one header marker
 *   /*__SLM:N*\/                          - one before each literal HTML
 *                                           chunk, where N is the source
 *                                           line that chunk starts on.
 *
 * At runtime the engine catches throwables out of fetch(), walks the
 * trace for the first frame in a *.tpl.php file, opens that file, and
 * walks backward from the reported error line looking for the nearest
 * preceding __SLM marker. That gives a source line — block-level worst
 * case, statement-level when there is HTML between Smarty tags.
 */
class SourceMap
{
    /**
     * Inject source-line markers into the given compiled PHP.
     *
     * Strategy: tokenize the compiled output, look at each T_INLINE_HTML
     * chunk, and search for that chunk in the .tpl source starting from
     * a forward-walking cursor. The cursor enforces left-to-right order
     * so a literal that appears multiple times in the source still maps
     * to the right occurrence.
     */
    public static function inject(string $compiled, string $source, string $sourcePath): string
    {
        $tokens = @token_get_all($compiled);
        if ($tokens === false || $tokens === []) {
            return $compiled;
        }

        $sourceCursor = 0;
        $sourceLen = strlen($source);
        $rebuilt = '<?php /*__SLF:'.self::escapeForComment($sourcePath).'*/ ?>';

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_INLINE_HTML) {
                [$chunkText, $endLine, $consumedTo] = self::locateChunk($token[1], $source, $sourceCursor, $sourceLen);

                $rebuilt .= $chunkText;

                // Marker is placed *after* each literal chunk and points at
                // the source line where the chunk ends — i.e. the line of
                // the Smarty tag that follows. Runtime errors fire inside
                // the compiled <?php block that consumes that tag, so the
                // nearest preceding marker is the right source line.
                if ($endLine !== null) {
                    $rebuilt .= '<?php /*__SLM:'.$endLine.'*/ ?>';
                    $sourceCursor = $consumedTo;
                }

                continue;
            }

            $rebuilt .= is_array($token) ? $token[1] : $token;
        }

        return $rebuilt;
    }

    /**
     * Find the literal chunk in the source. Returns [chunkText, endLine, newCursor]
     * where endLine is the source line containing the byte directly after
     * the chunk (i.e. where the next Smarty tag opens). null if we
     * couldn't locate the chunk.
     *
     * @return array{0: string, 1: int|null, 2: int}
     */
    private static function locateChunk(string $chunk, string $source, int $cursor, int $sourceLen): array
    {
        if ($chunk === '' || $cursor >= $sourceLen) {
            return [$chunk, null, $cursor];
        }

        // Try the full chunk first.
        $pos = strpos($source, $chunk, $cursor);
        if ($pos !== false) {
            $endOffset = $pos + strlen($chunk);

            return [$chunk, self::lineAt($source, $endOffset), $endOffset];
        }

        // Fall back to a prefix match — Smarty may have collapsed whitespace
        // at chunk boundaries (e.g. trim before a {tag}). Walk down from the
        // full chunk to a minimum useful length.
        $minLen = 8;
        for ($len = strlen($chunk) - 1; $len >= $minLen; $len--) {
            $prefix = substr($chunk, 0, $len);
            $pos = strpos($source, $prefix, $cursor);
            if ($pos !== false) {
                $endOffset = $pos + $len;

                return [$chunk, self::lineAt($source, $endOffset), $endOffset];
            }
        }

        return [$chunk, null, $cursor];
    }

    private static function lineAt(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }

    private static function escapeForComment(string $value): string
    {
        return str_replace(['*/', "\n", "\r"], ['*\/', ' ', ''], $value);
    }

    /**
     * Read the source path and source line embedded in a compiled file at
     * the given compiled-file line number. Returns null on the first
     * missing piece — the caller should fall back to letting the original
     * exception bubble.
     *
     * @return array{path: string, line: int}|null
     */
    public static function lookup(string $compiledFile, int $errorLine): ?array
    {
        if (! is_file($compiledFile) || ! is_readable($compiledFile)) {
            return null;
        }

        $lines = @file($compiledFile);
        if ($lines === false || $lines === []) {
            return null;
        }

        $path = null;
        foreach ($lines as $line) {
            if (preg_match('#__SLF:(.+?)\*/#', $line, $m) === 1) {
                $path = $m[1];
                break;
            }
        }

        if ($path === null) {
            return null;
        }

        $cap = min($errorLine, count($lines));
        for ($i = $cap - 1; $i >= 0; $i--) {
            if (preg_match('#__SLM:(\d+)\*/#', $lines[$i], $m) === 1) {
                return ['path' => $path, 'line' => (int) $m[1]];
            }
        }

        return ['path' => $path, 'line' => 1];
    }
}
