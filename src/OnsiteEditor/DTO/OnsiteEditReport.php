<?php

declare(strict_types=1);

namespace Leuffen\Shiller\OnsiteEditor\DTO;

/** @internal Model-generated report, independent of the edited text buffers. */
final class OnsiteEditReport
{
    public string $message;

    /** @var PendingTask[] */
    public array $pendingTasks;
}
