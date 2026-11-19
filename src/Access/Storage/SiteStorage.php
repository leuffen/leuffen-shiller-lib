<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Storage;

interface SiteStorage
{
    public function exists(string $path): bool;
    public function isDirectory(string $path): bool;
    public function read(string $path): string;

    /** @return list<array{name:string,type:string}> */
    public function list(string $path = ''): array;

    /** @param array<string,?string> $changes path => contents; null deletes */
    public function writeBatch(array $changes): void;
}
