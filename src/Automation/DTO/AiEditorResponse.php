<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation\DTO;

final class AiEditorResponse
{
    /**
     * @param string $text Insertion or replacement only; empty means no suggestion.
     * @param string $status Provider response status.
     * @param string|null $responseId Original conversation response ID, not the latest result ID.
     * @param array<string, mixed> $usage Harness usage export for the actual AI operation.
     * @param array<string, mixed>|null $initializationUsage Additional usage of a newly initialized conversation.
     */
    public function __construct(
        public string $text,
        public string $status,
        public ?string $responseId,
        public array $usage,
        public ?array $initializationUsage = null,
    ) {}
}
