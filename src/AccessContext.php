<?php

declare(strict_types=1);

namespace Leuffen\Shiller;

final class AccessContext
{
    public function __construct(public readonly string $role = 'reader') {}

    public function canWrite(): bool
    {
        return in_array($this->role, ['user', 'admin'], true);
    }
}
