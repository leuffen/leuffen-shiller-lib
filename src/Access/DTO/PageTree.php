<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\DTO;

use Leuffen\Shiller\Access\Info\Diagnostic;

final class PageTree
{
    public function __construct(
        public readonly TreeNode $root,
        public readonly array $diagnostics = [],
    ) {}

    public function toArray(): array
    {
        return [
            'root' => $this->root->toArray(),
            'diagnostics' => array_map(
                fn($diagnostic) => $diagnostic instanceof Diagnostic
                    ? $diagnostic->toArray()
                    : $diagnostic,
                $this->diagnostics,
            ),
        ];
    }
}
