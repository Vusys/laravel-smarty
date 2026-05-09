<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Security;

use Smarty\Smarty;

/**
 * Hardened \Smarty\Security policy for templates authored by untrusted
 * parties — end users, partners, multi-tenant template editors.
 *
 * Inherits {@see BalancedSecurityPolicy} and additionally:
 * - bans `{fetch}` (file_get_contents on URLs/paths — SSRF + arbitrary read),
 *   `{eval}` and `{include_php}` (direct dynamic-evaluation vectors).
 * - switches modifiers to an explicit allow-list so an unrelated Composer
 *   package registering a dangerous php_modifier can't be invoked from a
 *   template.
 * - forbids constant access (template enumeration leaks app internals even
 *   without a sensitive constant being defined).
 * - empties the streams allow-list, so only the implicit `template_dir`
 *   path can be reached.
 *
 * Hosts that register their own modifiers must subclass and append to
 * `$allowed_modifiers` — anything not on the list is rejected at render
 * time with a `\Smarty\Exception`.
 */
class StrictSecurityPolicy extends BalancedSecurityPolicy
{
    /**
     * Tags Strict bans on top of whatever Balanced bans. Composed via the
     * constructor so any future addition to Balanced is inherited rather
     * than silently lost.
     */
    public const ADDITIONAL_DISABLED_TAGS = ['fetch', 'include_php', 'eval'];

    /**
     * Curated allow-list. Mirrors Smarty 5's full default modifier set —
     * both the compiler-backed ones (Compile/Modifier/*) and the
     * callback-backed ones (Extension\DefaultExtension::getModifierCallback)
     * — minus `regex_replace` (catastrophic-backtracking DoS surface;
     * templates needing substitution should use `replace`).
     *
     * The trailing block is the modifiers this package itself registers
     * via Plugins/* — they must stay in sync with that directory or
     * Strict templates will reject calls to first-party helpers.
     *
     * @var array<int, string>
     */
    public $allowed_modifiers = [
        // Smarty 5 compiler-backed built-ins (Compile/Modifier/*):
        'cat', 'count_characters', 'count_paragraphs', 'count_sentences',
        'count_words', 'default', 'empty', 'escape', 'from_charset', 'indent',
        'is_array', 'isset', 'json_encode', 'lower', 'nl2br', 'noprint', 'raw',
        'round', 'string_format', 'strip', 'strip_tags', 'strlen', 'str_repeat',
        'substr', 'to_charset', 'unescape', 'upper', 'wordwrap',

        // Smarty 5 callback-backed built-ins (DefaultExtension::getModifierCallback):
        'capitalize', 'count', 'date_format', 'debug_print_var', 'explode',
        'implode', 'in_array', 'join', 'mb_wordwrap', 'number_format',
        'replace', 'spacify', 'split', 'truncate',
        // Deliberately NOT included: 'regex_replace' (DoS via catastrophic backtracking).

        // Package-shipped Laravel helpers (sync with src/Plugins/*):
        'currency', 'file_size', 'percentage', 'abbreviate', 'number_for_humans',
        'trans', 'trans_choice', 'json', 'markdown',
    ];

    /**
     * @var bool
     */
    public $allow_constants = false;

    /**
     * @var array<int, string>
     */
    public $streams = [];

    /**
     * @var array<int, string>
     */
    public $trusted_uri = [];

    /**
     * @var int
     */
    public $max_template_nesting = 25;

    public function __construct(Smarty $smarty)
    {
        parent::__construct($smarty);

        $this->disabled_tags = array_merge($this->disabled_tags, self::ADDITIONAL_DISABLED_TAGS);
    }
}
