<?php

namespace Vusys\LaravelSmarty;

use Vusys\LaravelSmarty\Plugins\LaravelPlugins;
use Illuminate\Filesystem\Filesystem;
use Smarty\Smarty;

class SmartyFactory
{
    public function __construct(
        protected Filesystem $files,
        protected array $config,
    ) {}

    public function make(array $templatePaths): BridgedSmarty
    {
        $smarty = new BridgedSmarty;

        $this->files->ensureDirectoryExists($this->config['compile_path']);
        $this->files->ensureDirectoryExists($this->config['cache_path']);

        $smarty->setTemplateDir($templatePaths);
        $smarty->setCompileDir($this->config['compile_path']);
        $smarty->setCacheDir($this->config['cache_path']);

        $smarty->setCaching(
            $this->config['caching'] ? Smarty::CACHING_LIFETIME_CURRENT : Smarty::CACHING_OFF
        );
        $smarty->setCacheLifetime($this->config['cache_lifetime']);
        $smarty->setForceCompile((bool) $this->config['force_compile']);
        $smarty->setDebugging((bool) $this->config['debugging']);

        foreach ($this->config['plugins_paths'] as $path) {
            $smarty->addPluginsDir($path);
        }

        LaravelPlugins::register($smarty);

        return $smarty;
    }
}
