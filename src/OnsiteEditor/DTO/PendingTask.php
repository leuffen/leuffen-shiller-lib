<?php

declare(strict_types=1);

namespace Leuffen\Shiller\OnsiteEditor\DTO;

/** A task the AI could not complete, suitable for manual or later processing. */
final class PendingTask
{
    public string $description;

    /** @var string[] Affected document-root-relative paths or text aliases. */
    public array $files;

    public string $reason;
}
