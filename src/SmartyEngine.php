<?php

namespace Vusys\LaravelSmarty;

use Illuminate\Contracts\View\Engine;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewException;
use Smarty\CompilerException;
use Smarty\Smarty;
use Throwable;
use Vusys\LaravelSmarty\Debug\SourceMap;
use Vusys\LaravelSmarty\Plugins\BlockState;

class SmartyEngine implements Engine
{
    public function __construct(
        protected Smarty $smarty,
        protected Filesystem $files,
    ) {}

    /**
     * @param  string  $path
     * @param  array<string, mixed>  $data
     */
    public function get($path, array $data = []): string
    {
        $directory = $this->files->dirname($path);

        if (! in_array($directory, (array) $this->smarty->getTemplateDir(), true)) {
            $this->smarty->addTemplateDir($directory);
        }

        $template = $this->smarty->createTemplate($this->files->basename($path), null, null, $this->smarty);

        $autoShareSession = ! array_key_exists('session', $data) && app()->bound('session');

        foreach ($data as $key => $value) {
            $template->assign($key, $value);
        }

        // Auto-shared $session is request-state, so mark it nocache: when
        // smarty.caching is on, uses of {$session.status} compile into a
        // {nocache} region instead of being baked into the cached output.
        if ($autoShareSession) {
            $template->assign('session', session()->all(), true);
        }

        try {
            return $template->fetch();
        } catch (Throwable $e) {
            throw $this->remapException($e, $path);
        } finally {
            // Block plugins like {auth} and {error} push/pop "outer value"
            // frames during open/close. If the body throws, Smarty never
            // re-invokes the close phase, so the entry would leak — and the
            // closure-static stack survives across renders under Octane.
            // Resetting at the render boundary keeps memory bounded and
            // guarantees a fresh stack for the next render.
            BlockState::reset();
        }
    }

    public function smarty(): Smarty
    {
        return $this->smarty;
    }

    /**
     * Walk the throwable chain (root + previous links) for the deepest
     * frame inside a Smarty-compiled file, then rewrite the exception to
     * point at the .tpl source via markers the compiler injected.
     *
     * The chain walk matters because Smarty runtime helpers like
     * CaptureRuntime catch a thrown user error in the body, run their own
     * teardown, and re-throw a "Not matching {capture}{/capture}" wrapper
     * whose own trace points at the runtime helper rather than the
     * .tpl.php. Without walking previous() the wrapper's frames would be
     * the only ones we see and we'd fall back to entryPath/line 1.
     */
    protected function remapException(Throwable $e, string $entryPath): Throwable
    {
        // CompilerException already carries the source filename and line
        // (Smarty parses them from the lexer state); just attach the View
        // suffix and preserve the rest.
        if ($e instanceof CompilerException) {
            return new ViewException(
                $e->getMessage().' (View: '.$e->getFile().')',
                0,
                1,
                $e->getFile(),
                $e->getLine(),
                $e,
            );
        }

        for ($current = $e; $current instanceof Throwable; $current = $current->getPrevious()) {
            $frames = array_merge(
                [['file' => $current->getFile(), 'line' => $current->getLine()]],
                $current->getTrace(),
            );

            foreach ($frames as $frame) {
                $file = $frame['file'] ?? null;
                $line = $frame['line'] ?? null;

                if (! is_string($file) || ! is_int($line)) {
                    continue;
                }

                if (! str_ends_with($file, '.tpl.php')) {
                    continue;
                }

                $mapped = SourceMap::lookup($file, $line);
                if ($mapped === null) {
                    continue;
                }

                // Wrap the exception that actually threw inside the
                // compiled file (current), not necessarily the outer one
                // ($e). When a runtime helper rethrows over the user's
                // error, the inner exception's message is the useful one
                // and BladeMapper's getPrevious() unwrap will land here.
                return new ViewException(
                    $current->getMessage().' (View: '.$mapped['path'].')',
                    0,
                    1,
                    $mapped['path'],
                    $mapped['line'],
                    $current,
                );
            }
        }

        return new ViewException(
            $e->getMessage().' (View: '.$entryPath.')',
            0,
            1,
            $entryPath,
            1,
            $e,
        );
    }
}
