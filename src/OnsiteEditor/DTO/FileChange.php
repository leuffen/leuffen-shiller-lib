<?php

declare(strict_types=1);

namespace Leuffen\Shiller\OnsiteEditor\DTO;

/** A proposed content change, never a persisted write. */
final readonly class FileChange
{
    public function __construct(
        public string $file,
        public string $operation,
        public string $originalContent,
        public string $content,
    ) {}
}
