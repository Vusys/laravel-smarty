<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Debug;

/**
 * Reads the __SLF / __SLM markers LineTrackingCompiler injects into
 * Smarty's compiled PHP and resolves a (compiled-file, error-line)
 * pair back to the original (.tpl source path, source line).
 *
 * The markers themselves are emitted at compile time by
 * LineTrackingCompiler::compileTag() and ::compileTemplate() — see
 * those methods for the contract. This class is the runtime side
 * (called from SmartyEngine and SmartyExceptionMapper).
 */
class SourceMap
{
    /**
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
            if (preg_match_all('#__SLM:(\d+)\*/#', $lines[$i], $m) >= 1) {
                // Multiple tags can compile onto one physical line of the
                // .tpl.php (Smarty packs adjacent tags). The rightmost
                // marker is the closest preceding tag for the error.
                $captures = $m[1];

                return ['path' => $path, 'line' => (int) end($captures)];
            }
        }

        return ['path' => $path, 'line' => 1];
    }
}
