<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\DTO;

final class FieldDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly array $options = [],
        public readonly array $presentation = [],
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
