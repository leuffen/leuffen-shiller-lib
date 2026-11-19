<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\DTO;

final class SiteConfig
{
    public function __construct(
        public readonly array $adapter,
        public readonly array $languages,
        public readonly string $defaultLanguage,
        public readonly array $languageLabels,
        public readonly string $url = '',
        public readonly string $baseurl = '',
        public readonly int $schemaVersion = 1,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
