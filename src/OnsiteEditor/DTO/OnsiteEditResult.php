<?php

declare(strict_types=1);

namespace Leuffen\Shiller\OnsiteEditor\DTO;

/** Result of a text-edit conversation; the application controls persistence. */
final readonly class OnsiteEditResult
{
    /**
     * @param string $status completed, partial, blocked or answered.
     * @param string $message User-facing response from the AI.
     * @param string|null $responseId Latest provider response ID, not an authorization token.
     * @param string|null $originalContent Content before this call for a single target.
     * @param string|null $content Complete current content for a single target.
     * @param bool $hasChanges Whether any actual content changed in this call.
     * @param list<FileChange> $changes Actual changed buffers, not model-reported writes.
     * @param list<PendingTask> $pendingTasks Model-reported unfinished work.
     * @param list<string> $availableSkills Discovered skill names.
     * @param array<string,mixed> $usage Usage of the last provider response.
     */
    public function __construct(
        public string $status,
        public string $message,
        public ?string $responseId,
        public ?string $originalContent,
        public ?string $content,
        public bool $hasChanges,
        public array $changes,
        public array $pendingTasks,
        public array $availableSkills,
        public array $usage,
    ) {}
}
