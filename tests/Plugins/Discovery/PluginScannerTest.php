<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins\Discovery;

use Composer\Autoload\ClassLoader;
use Vusys\LaravelSmarty\Exceptions\PluginRegistrationException;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginScanner;
use Vusys\LaravelSmarty\Tests\Fixtures\EmptyNamePlugin\Modifier as EmptyNameDerivedModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins\BadAttributeTypeModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins\TickFunction;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\AbstractBaseModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\AttributeTaggedThing;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\CustomNamedModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\DualTaggedThing;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\EmptyNameModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\LoudFunction;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\MultiWordModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\PlainHelper;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\PrivateNamedModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\SinceModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\StaticNamedModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\WrapBlock;
use Vusys\LaravelSmarty\Tests\TestCase;

class PluginScannerTest extends TestCase
{
    public function test_resolves_modifier_function_block_from_classname_suffix(): void
    {
        [$modifier] = PluginScanner::resolveDescriptor(SinceModifier::class);
        $this->assertSame('modifier', $modifier->type);
        $this->assertSame('since', $modifier->name);

        [$function] = PluginScanner::resolveDescriptor(LoudFunction::class);
        $this->assertSame('function', $function->type);
        $this->assertSame('loud', $function->name);

        [$block] = PluginScanner::resolveDescriptor(WrapBlock::class);
        $this->assertSame('block', $block->type);
        $this->assertSame('wrap', $block->name);
    }

    public function test_public_name_property_overrides_convention_default(): void
    {
        [$descriptor] = PluginScanner::resolveDescriptor(CustomNamedModifier::class);

        $this->assertSame('shouty', $descriptor->name);
    }

    public function test_private_name_property_is_ignored_in_favour_of_convention(): void
    {
        // The override contract is "public instance property with a literal
        // default". A private $name must not leak as the plugin name —
        // private state isn't a public API surface.
        [$descriptor] = PluginScanner::resolveDescriptor(PrivateNamedModifier::class);

        $this->assertSame('private_named', $descriptor->name);
    }

    public function test_static_name_property_is_ignored_in_favour_of_convention(): void
    {
        // Static $name is per-class, not per-instance; using it as the
        // tag name would surprise callers who expected instance state.
        [$descriptor] = PluginScanner::resolveDescriptor(StaticNamedModifier::class);

        $this->assertSame('static_named', $descriptor->name);
    }

    public function test_empty_string_name_property_falls_back_to_convention(): void
    {
        // Registering with the empty string would be unusable — guard
        // against treating $name = '' as a deliberate override.
        [$descriptor] = PluginScanner::resolveDescriptor(EmptyNameModifier::class);

        $this->assertSame('empty_name', $descriptor->name);
    }

    public function test_classname_equal_to_its_type_suffix_throws(): void
    {
        // `Modifier` ends in "Modifier" (so the type resolves) but strips
        // to an empty name — registering a nameless tag would be unusable,
        // so the scanner fails loud instead. Pin the whole message so a
        // reordered or dropped concat segment is caught.
        $this->expectException(PluginRegistrationException::class);
        $this->expectExceptionMessage(
            'Class '.EmptyNameDerivedModifier::class.' derives an empty modifier name from its classname. '
            .'Give it a longer name (e.g. SinceModifier, not just Modifier), or '
            .'declare a public $name property / #[SmartyPlugin(name: ...)] attribute.'
        );

        PluginScanner::resolveDescriptor(EmptyNameDerivedModifier::class);
    }

    public function test_attribute_takes_precedence_over_classname_convention(): void
    {
        [$descriptor] = PluginScanner::resolveDescriptor(AttributeTaggedThing::class);

        // The classname doesn't end in any suffix, but the attribute
        // makes the class a registered modifier all the same.
        $this->assertSame('modifier', $descriptor->type);
        $this->assertSame('shrunk', $descriptor->name);
    }

    public function test_repeatable_attribute_produces_one_descriptor_per_instance(): void
    {
        $result = PluginScanner::resolveDescriptor(DualTaggedThing::class);

        $this->assertCount(2, $result);

        $names = array_map(static fn ($d) => $d->name, $result);
        $this->assertContains('dual_a', $names);
        $this->assertContains('dual_b', $names);

        foreach ($result as $descriptor) {
            $this->assertSame('modifier', $descriptor->type);
            $this->assertSame(DualTaggedThing::class, $descriptor->class);
        }

        // Each attribute's cacheable flag is preserved independently.
        $byName = array_column($result, null, 'name');
        $this->assertTrue($byName['dual_a']->cacheable);
        $this->assertFalse($byName['dual_b']->cacheable);
    }

    public function test_repeatable_attribute_registers_both_names_in_namespace_scan(): void
    {
        $descriptors = PluginScanner::scan(['Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'], []);

        $names = array_map(static fn ($d) => $d->name, $descriptors);
        $this->assertContains('dual_a', $names);
        $this->assertContains('dual_b', $names);
    }

    public function test_classname_is_snake_cased_when_no_property_present(): void
    {
        [$descriptor] = PluginScanner::resolveDescriptor(MultiWordModifier::class);

        $this->assertSame('multi_word', $descriptor->name);
    }

    public function test_classes_without_suffix_or_attribute_are_skipped(): void
    {
        $this->assertSame([], PluginScanner::resolveDescriptor(PlainHelper::class));
    }

    public function test_abstract_classes_are_skipped(): void
    {
        $this->assertSame([], PluginScanner::resolveDescriptor(AbstractBaseModifier::class));
    }

    public function test_unknown_classes_resolve_to_empty_list(): void
    {
        $this->assertSame([], PluginScanner::resolveDescriptor('App\\Does\\Not\\Exist'));
    }

    public function test_attribute_with_invalid_type_throws(): void
    {
        $this->expectException(PluginRegistrationException::class);
        $this->expectExceptionMessage("declares type 'wrong-type'");

        PluginScanner::resolveDescriptor(BadAttributeTypeModifier::class);
    }

    public function test_namespace_scan_walks_subdirectories(): void
    {
        $descriptors = PluginScanner::scan(['Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'], []);

        $names = array_map(static fn ($d) => $d->type.':'.$d->name, $descriptors);

        // Subdir/NestedModifier picked up by the recursive walk
        $this->assertContains('modifier:nested', $names);
        // Top-level fixtures still picked up
        $this->assertContains('modifier:since', $names);
        $this->assertContains('function:loud', $names);
        $this->assertContains('block:wrap', $names);
        // Non-plugin classes silently ignored
        $this->assertNotContains('helper', $names);
    }

    public function test_overlapping_namespaces_yield_unique_descriptors(): void
    {
        // `App\Smarty` + `App\Smarty\Plugins` style overlap: the nested
        // namespace's classes are reachable through both scans. Without
        // the dedupe pass, PluginRegistrar would throw a duplicate-name
        // exception citing NestedModifier as its own duplicate.
        $descriptors = PluginScanner::scan([
            'Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins',
            'Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins\\Subdir',
        ], []);

        $keys = array_map(static fn ($d) => $d->type.':'.$d->name.':'.$d->class, $descriptors);

        $this->assertSame(array_values(array_unique($keys)), $keys);

        $nested = array_filter($keys, static fn (string $key): bool => str_contains($key, 'NestedModifier'));
        $this->assertCount(1, $nested);
    }

    public function test_manual_class_inside_scanned_namespace_registers_once(): void
    {
        $descriptors = PluginScanner::scan(
            ['Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'],
            [SinceModifier::class],
        );

        $since = array_filter(
            $descriptors,
            static fn ($d): bool => $d->type === 'modifier' && $d->name === 'since',
        );

        $this->assertCount(1, $since);
    }

    public function test_attribute_cacheable_flag_lands_on_descriptor(): void
    {
        [$descriptor] = PluginScanner::resolveDescriptor(TickFunction::class);

        $this->assertFalse($descriptor->cacheable);

        // Convention-resolved classes have no opt-out channel and default
        // to cacheable, matching registerPlugin()'s own default.
        [$bySuffix] = PluginScanner::resolveDescriptor(SinceModifier::class);
        $this->assertTrue($bySuffix->cacheable);
    }

    public function test_namespace_scan_accepts_a_leading_backslash(): void
    {
        // Config-supplied namespaces may arrive with a leading separator
        // ("\\Acme\\Plugins"). classesIn() trims the slash before passing
        // the prefix to Composer's PSR-4 resolver — without that trim,
        // Composer's prefix table never matches and the scan finds
        // nothing.
        $descriptors = PluginScanner::scan(['\\Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'], []);

        $names = array_map(static fn ($d) => $d->name, $descriptors);

        $this->assertContains('since', $names);
    }

    public function test_manually_registered_unrecognized_class_throws(): void
    {
        $this->expectException(PluginRegistrationException::class);
        $this->expectExceptionMessage('cannot be registered as a Smarty plugin');

        PluginScanner::scan([], [PlainHelper::class]);
    }

    public function test_empty_or_whitespace_only_namespace_is_silently_skipped(): void
    {
        // Namespaces from config arrive as user-supplied strings; an
        // accidentally-empty or whitespace-only entry shouldn't blow
        // up the scan or somehow walk the entire filesystem.
        $descriptors = PluginScanner::scan(['', '\\\\'], []);

        $this->assertSame([], $descriptors);
    }

    public function test_fingerprint_inputs_silently_skips_empty_or_whitespace_only_namespace(): void
    {
        // Same defensive guard as scan() / classesIn(): a
        // whitespace-only namespace must not trigger a real filesystem
        // walk. The hash should match the no-input baseline.
        $baseline = PluginScanner::fingerprintInputs([], []);

        $this->assertSame($baseline, PluginScanner::fingerprintInputs(['', '\\\\'], []));
    }

    public function test_fingerprint_inputs_continues_past_empty_namespace_to_a_real_one(): void
    {
        // An empty entry must be skipped, not used as a terminator —
        // a `break` would drop the real namespace that follows and
        // produce the no-input fingerprint instead of the real one.
        $real = 'Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins';

        $direct = PluginScanner::fingerprintInputs([$real], []);
        $withEmptyFirst = PluginScanner::fingerprintInputs(['', $real], []);

        $this->assertSame($direct, $withEmptyFirst);
        $this->assertNotSame(
            PluginScanner::fingerprintInputs([], []),
            $withEmptyFirst,
            'Real namespace must still contribute to the hash.',
        );
    }

    public function test_fingerprint_inputs_continues_past_nonexistent_manual_class(): void
    {
        // A class_exists() miss must continue to the next manual entry
        // — break would skip the real class and yield the empty-input
        // fingerprint.
        $baseline = PluginScanner::fingerprintInputs([], [SinceModifier::class]);
        $withGhostFirst = PluginScanner::fingerprintInputs(
            [],
            ['App\\Does\\Not\\Exist', SinceModifier::class],
        );

        $this->assertSame($baseline, $withGhostFirst);
        $this->assertNotSame(
            PluginScanner::fingerprintInputs([], []),
            $withGhostFirst,
        );
    }

    public function test_namespace_spanning_multiple_directories_scans_all_of_them(): void
    {
        // Composer permits one PSR-4 prefix to map to several roots
        // (["src/", "modules/"]). The scan must iterate every resolved
        // directory — a `break` after the first would silently drop
        // half the app's plugins.
        $this->registerMultiDirNamespace();

        $descriptors = PluginScanner::scan(['Vusys\\LaravelSmarty\\Tests\\Fixtures\\MultiDir'], []);

        $names = array_map(static fn ($d) => $d->name, $descriptors);

        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);
    }

    public function test_fingerprint_covers_every_directory_of_a_multi_root_namespace(): void
    {
        // The cache-invalidation fingerprint has the same multi-root
        // obligation as the scan: an edit in the *second* directory
        // must change the hash, or stale caches survive plugin edits.
        $this->registerMultiDirNamespace();

        $namespace = 'Vusys\\LaravelSmarty\\Tests\\Fixtures\\MultiDir';
        $before = PluginScanner::fingerprintInputs([$namespace], []);

        $file = __DIR__.'/../../Fixtures/MultiDirB/BetaModifier.php';
        $original = filemtime($file);
        $this->assertIsInt($original);

        try {
            touch($file, $original + 2);
            clearstatcache(true, $file);

            $this->assertNotSame($before, PluginScanner::fingerprintInputs([$namespace], []));
        } finally {
            touch($file, $original);
            clearstatcache(true, $file);
        }
    }

    private function registerMultiDirNamespace(): void
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
                // addPsr4 appends on repeat calls; the scanner's
                // directory dedupe makes that harmless.
                $autoloader[0]->addPsr4('Vusys\\LaravelSmarty\\Tests\\Fixtures\\MultiDir\\', [
                    __DIR__.'/../../Fixtures/MultiDirA',
                    __DIR__.'/../../Fixtures/MultiDirB',
                ]);

                return;
            }
        }
    }

    public function test_non_php_files_in_scanned_directory_are_ignored(): void
    {
        // tests/Fixtures/Plugins/notes.txt sits next to the plugin classes;
        // the scanner must skip it rather than try to derive a class name
        // from a `.txt` filename.
        $descriptors = PluginScanner::scan(['Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'], []);

        // A non-php file would have produced a class name like
        // `…\\Plugins\\notes` and resolveDescriptor() would still return
        // null, so the assertion is structural: discovery still completes
        // and finds the actual plugins.
        $names = array_map(static fn ($d) => $d->name, $descriptors);
        $this->assertContains('since', $names);
        $this->assertContains('loud', $names);
    }
}
