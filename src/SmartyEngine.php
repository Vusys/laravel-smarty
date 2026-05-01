<?php

namespace Vusys\LaravelSmarty;

use Illuminate\Contracts\View\Engine;
use Illuminate\Filesystem\Filesystem;
use Smarty\Smarty;

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

        return $template->fetch();
    }

    public function smarty(): Smarty
    {
        return $this->smarty;
    }
}
