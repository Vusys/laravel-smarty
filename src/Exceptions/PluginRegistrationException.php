<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Exceptions;

use LogicException;

/**
 * Thrown when class-backed plugin discovery hits an irrecoverable
 * configuration error: a manually-registered class that doesn't match
 * the convention or carry an attribute, an attribute with an unknown
 * `$type`, or two discovered classes claiming the same `(type, name)`
 * pair. Silent shadowing in any of these cases is exactly the bug we'd
 * be inviting, so we fail loud at boot rather than at template render.
 */
class PluginRegistrationException extends LogicException
{
    public static function unrecognizedClass(string $class): self
    {
        return new self(
            "Class {$class} cannot be registered as a Smarty plugin: it has no #[SmartyPlugin] attribute "
            .'and its name does not end in Modifier, Function, or Block.'
        );
    }

    public static function invalidType(string $type, string $class): self
    {
        return new self(
            "#[SmartyPlugin] on {$class} declares type '{$type}'; expected one of: modifier, function, block."
        );
    }

    public static function duplicateName(string $type, string $name, string $existingClass, string $newClass): self
    {
        return new self(
            "Two class-backed Smarty plugins claim the same {$type} name '{$name}': "
            ."{$existingClass} (registered first) and {$newClass}. "
            .'Rename one of them, or change the #[SmartyPlugin] attribute / public $name property.'
        );
    }
}
