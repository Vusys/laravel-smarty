<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Debug;

use Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Extends Laravel's BladeMapper so the same exception-page treatment
 * Blade enjoys (compiled-PHP frame paths rewritten to the .blade.php
 * source) also applies to Smarty's compiled .tpl.php files.
 *
 * Laravel's Renderer instantiates BladeMapper directly with no
 * extension point, so we rebind the BladeMapper container key to this
 * subclass in the service provider.
 *
 * The mapping itself is delegated to SourceMap::lookup(), which reads
 * the __SLF / __SLM markers the SmartyFactory postfilter injects at
 * compile time.
 */
class SmartyExceptionMapper extends BladeMapper
{
    public function map(FlattenException $exception)
    {
        $exception = parent::map($exception);

        // After parent has unwrapped any ViewException and rewritten
        // Blade frames, walk the trace once more and rewrite any frame
        // whose file is still a Smarty-compiled .tpl.php.
        $trace = array_map(function (array $frame): array {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            if (! is_string($file) || ! is_int($line)) {
                return $frame;
            }

            if (! str_ends_with($file, '.tpl.php')) {
                return $frame;
            }

            $mapped = SourceMap::lookup($file, $line);
            if ($mapped === null) {
                return $frame;
            }

            $frame['file'] = $mapped['path'];
            $frame['line'] = $mapped['line'];

            return $frame;
        }, $exception->getTrace());

        // FlattenException::$trace is private; assign via bound closure
        // — same idiom BladeMapper itself uses.
        (function () use ($trace) {
            $this->trace = $trace;
        })->call($exception);

        return $exception;
    }
}
