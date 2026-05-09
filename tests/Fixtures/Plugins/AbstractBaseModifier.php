<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures\Plugins;

/**
 * Abstract — non-instantiable, so the scanner should skip it even
 * though the classname matches the convention.
 */
abstract class AbstractBaseModifier
{
    abstract public function __invoke(mixed $value): mixed;
}
