<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Helper;

use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;

/** Writes shared project files without changing permissions of existing entries. */
final class SharedWritePermissions
{
    // Nur hier neu angelegte Verzeichnisse und Dateien erhalten Shared-Write-Rechte.
    public static function write(PhoreFile $file, PhoreDirectory $root, string $content): void
    {
        $exists = $file->exists();
        $relative = str_replace('\\', '/', (string) $file->getRelPath($root));
        $parts = explode('/', $relative);
        array_pop($parts);
        $directory = $root;
        foreach ($parts as $part) {
            $directory = $directory->withSubPath($part)->asDirectory();
            if (!$directory->exists()) {
                $directory->mkdir(0777)->asFile()->chmod(0777);
            }
        }
        $file->set_contents($content);
        if (!$exists) {
            $file->chmod(0666);
        }
    }
}
