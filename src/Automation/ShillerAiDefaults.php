<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

/** Shared default AI settings for Shiller commands and actions. */
final class ShillerAiDefaults
{
    public const MODEL = 'gpt-6.1-sol';
    public const EDITOR_COMPLETION_MODEL = 'gpt-6-luna';
    public const EDITOR_EDIT_MODEL = 'gpt-6.2-sol';
}
