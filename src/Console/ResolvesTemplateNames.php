<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Console;

use Smarty\Smarty;

/**
 * SmartyEngine renders templates under their *absolute* path (so views
 * sharing a basename can't shadow each other), and Smarty keys compile
 * and cache files on that name. A bare `--file=hello.tpl` therefore has
 * to be resolved against the template dirs to the same absolute name
 * before clearCache()/clearCompiledTemplate() can match anything.
 */
trait ResolvesTemplateNames
{
    private function resolveTemplateName(Smarty $smarty, string $file): string
    {
        if (str_starts_with($file, '/') || (strlen($file) > 1 && $file[1] === ':')) {
            return $file;
        }

        foreach ((array) $smarty->getTemplateDir() as $dir) {
            $candidate = rtrim((string) $dir, '/\\').DIRECTORY_SEPARATOR.$file;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $file;
    }
}
