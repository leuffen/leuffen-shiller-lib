<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

final class FileListing
{
    public function __construct(
        public readonly array $entries,
        public readonly array $diagnostics = [],
    ) {}

    public function toArray(): array
    {
        return [
            'entries' => array_map(
                fn(TreeNode $node): array => $node->toArray(),
                $this->entries,
            ),
            'diagnostics' => array_map(
                fn($diagnostic) => $diagnostic instanceof Diagnostic
                    ? $diagnostic->toArray()
                    : $diagnostic,
                $this->diagnostics,
            ),
        ];
    }
}
