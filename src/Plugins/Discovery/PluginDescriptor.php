<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins\Discovery;

/**
 * Resolved metadata for one class-backed plugin: the Smarty plugin type
 * (`modifier`/`function`/`block`), the tag/modifier name templates use
 * to invoke it, and the FQCN that will be resolved through the
 * container at invocation time.
 */
final class PluginDescriptor
{
    /**
     * @param  'modifier'|'function'|'block'  $type
     * @param  class-string  $class
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $class,
    ) {}

    /**
     * @return array{type: 'modifier'|'function'|'block', name: string, class: class-string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'class' => $this->class,
        ];
    }

    /**
     * @param  array{type: string, name: string, class: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $type = $payload['type'];
        if (! in_array($type, ['modifier', 'function', 'block'], true)) {
            throw new \InvalidArgumentException("Unknown plugin type '{$type}'.");
        }

        /** @var class-string $class */
        $class = $payload['class'];

        return new self($type, $payload['name'], $class);
    }
}
