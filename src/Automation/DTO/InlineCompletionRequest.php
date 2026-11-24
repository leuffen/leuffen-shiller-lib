<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation\DTO;

/** Mirrors CodeEditorInlineCompletionRequest without the browser's AbortSignal. */
final class InlineCompletionRequest
{
    /** Editor language, e.g. markdown, html, css, scss, javascript, typescript or text. */
    public string $language;
    public string $codeBefore;
    public string $codeAfter;
    /** Absolute UTF-16 cursor offset in the editor document. */
    public int $cursor;
    /** Initial conversation response ID; null starts a new conversation. */
    public ?string $previousResponseId = null;
}
