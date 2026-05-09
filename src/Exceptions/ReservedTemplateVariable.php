<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Exceptions;

use LogicException;

/**
 * Thrown when user-supplied view data contains a key that collides with
 * one of the auto-shared wrapper variables ($auth, $request, $session,
 * $route, $errors). Treating this as a programmer error — silently
 * letting user data win would mask typos and produce confusing template
 * output.
 *
 * Exception: an `errors` key whose value is a `ViewErrorBag` is
 * suppressed silently in `SmartyEngine::get()`, because Laravel's stock
 * `ShareErrorsFromSession` middleware injects exactly that on every
 * request — and the package's `$errors` wrapper wraps the same bag, so
 * the visible template behaviour is identical.
 */
class ReservedTemplateVariable extends LogicException
{
    public static function for(string $name): self
    {
        return new self(
            "View variable \${$name} is reserved by laravel-smarty's auto-share. "
            .'Rename your view-data key.'
        );
    }
}
