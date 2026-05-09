<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewObject;

/**
 * View composers registered against `{extends}`-pulled layouts and
 * `{include}`-d partials must propagate their `$view->with(...)` data
 * onto the actual sub-template render — same contract Blade gives.
 *
 * Without the data-transcription hop in `SmartyResource::fireForTemplate`,
 * the synthetic View handed to the dispatcher accepts the writes and
 * is then garbage-collected, silently dropping the composer's data.
 * These tests pin the contract end-to-end.
 */
class SubTemplateComposerTest extends TestCase
{
    public function test_composing_composer_data_reaches_extends_layout_render(): void
    {
        View::composer('composer.layout', static function (ViewObject $view): void {
            $view->with('layoutComposing', 'COMPOSED-VALUE');
        });

        $output = view('composer.child')->render();

        $this->assertStringContainsString('[layout-from-composing=COMPOSED-VALUE]', $output);
    }

    public function test_creating_composer_data_reaches_extends_layout_render(): void
    {
        // `creating:` listeners fire before `composing:` and Blade lets
        // them mutate $view too. Both events run through fireForTemplate,
        // so both kinds of writes must end up assigned to the Template.
        View::creator('composer.layout', static function (ViewObject $view): void {
            $view->with('layoutCreating', 'CREATED-VALUE');
        });

        $output = view('composer.child')->render();

        $this->assertStringContainsString('[layout-from-creating=CREATED-VALUE]', $output);
    }

    public function test_composer_data_for_partials_reaches_include_render(): void
    {
        // Same fix applies to {include} (sub-templates loaded via the
        // same doCreateTemplate code path). Composer for the included
        // partial must reach its render scope.
        View::composer('composer.sidebar', static function (ViewObject $view): void {
            $view->with('sidebarComposing', 'PARTIAL-VALUE');
        });

        $output = view('composer.child')->render();

        $this->assertStringContainsString('[sidebar-from-composing=PARTIAL-VALUE]', $output);
    }

    public function test_layout_composer_data_is_visible_inside_child_blocks_too(): void
    {
        // Documents Smarty's `{extends}` scope semantics rather than a
        // contract of *this fix* per se: a `{block}` body in the child
        // executes within the layout Template's variable scope, so a
        // composer for the layout populating `$childOwn` is visible
        // when the child references it inside `{block name="content"}`.
        // Same shape as Blade — both engines share variables across
        // the @extends chain — but worth pinning because users
        // sometimes expect tighter isolation.
        View::composer('composer.layout', static function (ViewObject $view): void {
            $view->with('childOwn', 'LAYOUT-VALUE');
        });

        $output = view('composer.child')->render();

        $this->assertStringContainsString('child-content[LAYOUT-VALUE]', $output);
    }

    public function test_view_share_still_reaches_extends_layout_unchanged(): void
    {
        // Foil for the composer-fix: confirms View::share continues to
        // populate the layout's scope (it always did, via the engine's
        // shared-data path). The fix only touches the composer-via-
        // `with()` route, leaving share unaffected.
        View::share('layoutComposing', 'SHARED-VALUE');

        $output = view('composer.child')->render();

        $this->assertStringContainsString('[layout-from-composing=SHARED-VALUE]', $output);
    }

    public function test_composer_with_overrides_view_share_for_layout_render(): void
    {
        // Precedence check: composer's $view->with() data wins over
        // View::share data for the same key, matching Blade's contract
        // where View::with() data overrides shared during gatherData().
        View::share('layoutComposing', 'SHARED-VALUE');
        View::composer('composer.layout', static function (ViewObject $view): void {
            $view->with('layoutComposing', 'COMPOSER-WINS');
        });

        $output = view('composer.child')->render();

        $this->assertStringContainsString('[layout-from-composing=COMPOSER-WINS]', $output);
        $this->assertStringNotContainsString('SHARED-VALUE', $output);
    }

    public function test_composer_data_lands_in_first_render_under_caching_true(): void
    {
        // With smarty.caching=true, the layout's rendered output is
        // baked into a cache file on first render — including any data
        // composers assigned via `$view->with(...)`. Subsequent renders
        // serve the cached output and do NOT re-run composers (that's
        // the whole point of caching). Per-render data still refreshes
        // through the existing nocache plugin / auto-shared wrapper
        // path, which is unaffected by this fix.
        config([
            'smarty.caching' => true,
            'smarty.cache_lifetime' => 60,
            'smarty.force_compile' => false,
        ]);

        View::composer('composer.layout', static function (ViewObject $view): void {
            $view->with('layoutComposing', 'CACHED-VALUE');
        });

        $first = view('composer.child')->render();
        $this->assertStringContainsString('[layout-from-composing=CACHED-VALUE]', $first);

        // Second render: cache hit. The composer-assigned value is part
        // of the cached output, so it surfaces just like any other
        // cached fragment — exactly the contract Smarty's caching
        // guarantees for non-nocache template data.
        $second = view('composer.child')->render();
        $this->assertStringContainsString('[layout-from-composing=CACHED-VALUE]', $second);
    }

    public function test_no_composer_registered_keeps_default_unset_marker(): void
    {
        // Negative bound: with no composer registered, the layout's
        // `{$layoutComposing|default:'unset'}` stays at the default,
        // proving the test fixtures don't accidentally pre-populate
        // the variable from elsewhere.
        Event::fake(['*: composer.layout']);

        $output = view('composer.child')->render();

        $this->assertStringContainsString('[layout-from-composing=unset]', $output);
        $this->assertStringContainsString('[layout-from-creating=unset]', $output);
    }
}
