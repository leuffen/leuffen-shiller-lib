<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Info;

final class Capabilities
{
    public function __construct(
        public readonly bool $read = true,
        public readonly bool $write = false,
        public readonly bool $create = false,
        public readonly bool $rename = false,
        public readonly bool $delete = false,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
