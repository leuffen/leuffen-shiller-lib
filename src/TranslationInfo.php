<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

final class TranslationInfo
{
    public function __construct(
        public readonly string $language,
        public readonly string $path,
        public readonly bool $exists,
        public readonly bool $isRootDocument,
        public readonly ?bool $published,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
