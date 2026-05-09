<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins\Discovery;

use InvalidArgumentException;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginDescriptor;
use Vusys\LaravelSmarty\Tests\TestCase;

class PluginDescriptorTest extends TestCase
{
    public function test_to_array_round_trips_through_from_array(): void
    {
        $original = new PluginDescriptor('block', 'wrap', 'App\\Smarty\\Plugins\\WrapBlock');

        $copy = PluginDescriptor::fromArray($original->toArray());

        $this->assertSame($original->type, $copy->type);
        $this->assertSame($original->name, $copy->name);
        $this->assertSame($original->class, $copy->class);
    }

    public function test_from_array_throws_on_unknown_type(): void
    {
        // The cache file is the only realistic path that hands an
        // unknown type to fromArray() — defends against a hand-edited
        // or schema-drifted cache shipping a bogus entry.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown plugin type 'gadget'");

        PluginDescriptor::fromArray([
            'type' => 'gadget',
            'name' => 'x',
            'class' => 'App\\X',
        ]);
    }
}
