<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\FileListing;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): FileListing {
    $site = new SchillerDir($root);
    $listing = $site->files('leistungen', recursive: true);

    // FileListing->entries: list<FileEntry>; Unterordner stehen in entry->children.
    // Für nur eine Ebene: $site->files('leistungen', recursive: false).
    // Erwartete Projektion für die Fixture:
    // ['path' => 'leistungen', 'entries' => [
    //   ['path' => 'leistungen/diagnostik.md', 'kind' => 'page', 'children' => []]
    // ], 'diagnostics' => []]
    // Weitere vorhandene Dateien erscheinen ebenfalls; gesperrte Pfade niemals.
    // kind ist in PHP ein FileKind-Enum, in JSON sein String-Wert.
    return $listing;
};
