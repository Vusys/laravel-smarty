<?php

namespace Vusys\LaravelSmarty;

use Illuminate\Contracts\View\Engine;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewException;
use Smarty\Smarty;
use Throwable;
use Vusys\LaravelSmarty\Debug\SourceMap;

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

        foreach ($data as $key => $value) {
            $template->assign($key, $value);
        }

        try {
            return $template->fetch();
        } catch (Throwable $e) {
            throw $this->remapException($e, $path);
        }
    }

    public function smarty(): Smarty
    {
        return $this->smarty;
    }

    /**
     * Walk the trace for the deepest frame inside a Smarty-compiled file
     * and rewrite the exception to point at the .tpl source via markers
     * the postfilter injected at compile time.
     */
    protected function remapException(Throwable $e, string $entryPath): Throwable
    {
        $frames = array_merge(
            [['file' => $e->getFile(), 'line' => $e->getLine()]],
            $e->getTrace(),
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

            return new ViewException(
                $e->getMessage().' (View: '.$mapped['path'].')',
                0,
                1,
                $mapped['path'],
                $mapped['line'],
                $e,
            );
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
