<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\DTO;

use Leuffen\Shiller\Access\Info\FileKind;
use Leuffen\Shiller\Access\Info\TranslationInfo;

final class ShillerTreeData
{
    public function __construct(
        public readonly ?string $path,
        public readonly FileKind $kind,
        public readonly ?FileEntry $file = null,
        public readonly array $metadata = [],
        public readonly array $translations = [],
        public readonly bool $hasChildren = false,
        public readonly bool $childrenLoaded = true,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'kind' => $this->kind->value,
            'file' => $this->file?->toArray(),
            'metadata' => (object) $this->metadata,
            'translations' => (object) array_map(
                fn($translation) => $translation instanceof TranslationInfo
                    ? $translation->toArray()
                    : $translation,
                $this->translations,
            ),
            'hasChildren' => $this->hasChildren,
            'childrenLoaded' => $this->childrenLoaded,
        ];
    }
}
