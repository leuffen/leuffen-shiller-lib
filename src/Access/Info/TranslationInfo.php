<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Info;

final class TranslationInfo
{
    public function __construct(
        public readonly string $language,
        public readonly string $path,
        public readonly bool $exists,
        public readonly bool $isRootDocument,
        public readonly ?bool $published,
        public readonly ?string $linkUrl = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
