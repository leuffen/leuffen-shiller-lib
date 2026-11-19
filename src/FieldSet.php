<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

final class FieldSet
{
    /** @param list<FieldDefinition> $fields */
    public function __construct(public readonly array $fields = []) {}

    public function toArray(): array
    {
        return [
            'fields' => array_map(
                fn(FieldDefinition $field): array => $field->toArray(),
                $this->fields,
            ),
        ];
    }
}
