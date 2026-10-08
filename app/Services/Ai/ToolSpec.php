<?php

namespace App\Services\Ai;

use Closure;

/**
 * A vendor-neutral tool definition handed to a provider driver.
 *
 * `parameters` maps a name to ['type' => 'string'|'number'|'boolean'|'enum',
 * 'description' => string, 'required' => bool, 'options' => array (enum only)].
 * The handler receives the model's arguments and returns text for the model.
 */
final class ToolSpec
{
    /** @param  array<string, array<string, mixed>>  $parameters */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly Closure $handler,
    ) {}

    public function call(array $arguments): string
    {
        return ($this->handler)($arguments);
    }

    /** JSON schema, for drivers that talk to a vendor API directly. */
    public function jsonSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $name => $param) {
            $property = ['description' => $param['description'] ?? ''];
            if (($param['type'] ?? 'string') === 'enum') {
                $property['type'] = 'string';
                $property['enum'] = array_values($param['options'] ?? []);
            } else {
                $property['type'] = $param['type'] ?? 'string';
            }
            $properties[$name] = $property;

            if ($param['required'] ?? false) {
                $required[] = $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => (object) $properties,
            'required' => $required,
        ];
    }
}
