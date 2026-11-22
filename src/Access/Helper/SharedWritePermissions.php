<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Access\Helper;

use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;

/**
 * Normalizes files written into a shared Shiller project volume.
 *
 * Files are writable by all users (0666), and parent directories inside the
 * project root are traversable and writable by all users (0777).
 */
final class SharedWritePermissions
{
    public static function apply(PhoreFile $file, PhoreDirectory $root): void
    {
        $relative = str_replace('\\', '/', (string) $file->getRelPath($root));
        $parts = explode('/', $relative);
        array_pop($parts);

        $directory = $root;
        foreach ($parts as $part) {
            $directory = $directory->withSubPath($part)->asDirectory();
            $directory->asFile()->chmod(0777);
        }

        $file->chmod(0666);
    }
}
