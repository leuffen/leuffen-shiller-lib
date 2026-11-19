<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Info;

final class Diagnostic
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $path = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
