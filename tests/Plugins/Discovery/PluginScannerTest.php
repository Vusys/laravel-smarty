<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins\Discovery;

use Vusys\LaravelSmarty\Exceptions\PluginRegistrationException;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginScanner;
use Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins\BadAttributeTypeModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\AbstractBaseModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\AttributeTaggedThing;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\CustomNamedModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\LoudFunction;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\MultiWordModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\PlainHelper;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\SinceModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\WrapBlock;
use Vusys\LaravelSmarty\Tests\TestCase;

class PluginScannerTest extends TestCase
{
    public function test_resolves_modifier_function_block_from_classname_suffix(): void
    {
        $modifier = PluginScanner::resolveDescriptor(SinceModifier::class);
        $this->assertNotNull($modifier);
        $this->assertSame('modifier', $modifier->type);
        $this->assertSame('since', $modifier->name);

        $function = PluginScanner::resolveDescriptor(LoudFunction::class);
        $this->assertNotNull($function);
        $this->assertSame('function', $function->type);
        $this->assertSame('loud', $function->name);

        $block = PluginScanner::resolveDescriptor(WrapBlock::class);
        $this->assertNotNull($block);
        $this->assertSame('block', $block->type);
        $this->assertSame('wrap', $block->name);
    }

    public function test_public_name_property_overrides_convention_default(): void
    {
        $descriptor = PluginScanner::resolveDescriptor(CustomNamedModifier::class);

        $this->assertNotNull($descriptor);
        $this->assertSame('shouty', $descriptor->name);
    }

    public function test_attribute_takes_precedence_over_classname_convention(): void
    {
        $descriptor = PluginScanner::resolveDescriptor(AttributeTaggedThing::class);

        // The classname doesn't end in any suffix, but the attribute
        // makes the class a registered modifier all the same.
        $this->assertNotNull($descriptor);
        $this->assertSame('modifier', $descriptor->type);
        $this->assertSame('shrunk', $descriptor->name);
    }

    public function test_classname_is_snake_cased_when_no_property_present(): void
    {
        $descriptor = PluginScanner::resolveDescriptor(MultiWordModifier::class);

        $this->assertNotNull($descriptor);
        $this->assertSame('multi_word', $descriptor->name);
    }

    public function test_classes_without_suffix_or_attribute_are_skipped(): void
    {
        $this->assertNull(PluginScanner::resolveDescriptor(PlainHelper::class));
    }

    public function test_abstract_classes_are_skipped(): void
    {
        $this->assertNull(PluginScanner::resolveDescriptor(AbstractBaseModifier::class));
    }

    public function test_unknown_classes_resolve_to_null(): void
    {
        $this->assertNull(PluginScanner::resolveDescriptor('App\\Does\\Not\\Exist'));
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

    public function test_manually_registered_unrecognized_class_throws(): void
    {
        $this->expectException(PluginRegistrationException::class);
        $this->expectExceptionMessage('cannot be registered as a Smarty plugin');

        PluginScanner::scan([], [PlainHelper::class]);
    }
}
