<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\DTO;

final class TreeNode
{
    /** @param list<TreeNode> $children */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly array $children,
        public readonly ShillerTreeData $data,
        private readonly ?Document $document = null,
    ) {}

    public function isLeaf(): bool
    {
        return !$this->data->hasChildren;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'children' => array_map(
                fn(self $node): array => $node->toArray(),
                $this->children,
            ),
            'data' => $this->data->toArray(),
        ];
    }
}
