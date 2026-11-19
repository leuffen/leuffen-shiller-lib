<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

final class FileEntry
{
    public function __construct(
        public readonly string $path,
        public readonly FileKind $kind,
    ) {}

    public function toArray(): array
    {
        return ['path' => $this->path, 'kind' => $this->kind->value];
    }
}
