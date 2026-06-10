<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Compile;

use Smarty\Compile\Modifier\ModifierCompilerInterface;
use Smarty\Extension\Base;

/**
 * Provides a {@see NocacheModifierCompiler} for a fixed list of modifier
 * names. Registered by the plugin groups whose modifiers are request- or
 * locale-coupled (TranslationPlugins, NumberPlugins) alongside the
 * runtime callbacks — the callback stays the single implementation; this
 * extension only changes how calls to it compile under output caching.
 *
 * Each group adds its own instance guarded by its own availability
 * checks, so e.g. the |currency compiler is only reachable when
 * NumberPlugins actually registered |currency (Laravel 11+) — returning
 * a compiler for an unregistered modifier would turn the compile-time
 * "unknown modifier" error into a runtime TypeError.
 */
class NocacheModifiersExtension extends Base
{
    /** @var array<int, string> */
    private readonly array $modifiers;

    public function __construct(string ...$modifiers)
    {
        // array_values: a spread with string keys would otherwise leak
        // them into the variadic array.
        $this->modifiers = array_values($modifiers);
    }

    public function getModifierCompiler(string $modifier): ?ModifierCompilerInterface
    {
        return in_array($modifier, $this->modifiers, true)
            ? new NocacheModifierCompiler($modifier)
            : null;
    }
}
