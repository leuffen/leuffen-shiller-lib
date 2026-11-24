<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation\DTO;

/** Mirrors CodeEditorInlineEditRequest without the browser's AbortSignal. */
final class InlineEditRequest
{
    public string $language;
    public string $codeBefore;
    /** Full lines selected for replacement, or an empty string for insertion at the cursor. */
    public string $selection;
    public string $codeAfter;
    /** Absolute UTF-16 start and end offsets (end exclusive). */
    public int $from;
    public int $to;
    /** User instruction for replacing the selection or inserting new text. */
    public string $prompt;
    /** Initial conversation response ID; null starts a new conversation. */
    public ?string $previousResponseId = null;
}
