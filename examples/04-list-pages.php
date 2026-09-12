<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\PageTree;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): PageTree {
    $site = new SchillerDir($root);
    $tree = $site->pages('leistungen');

    // PageTree->root: PageFolder
    // root->folders: list<PageFolder>, rekursiv
    // root->pages: list<PageGroup>
    // Erwartete Gruppe:
    // id = 'leistungen/diagnostik.md', primaryPath = 'leistungen/diagnostik.md'
    // translations enthält de und en, aus den gespiegelten Pfaden
    // leistungen/diagnostik.md und en/leistungen/diagnostik.md.
    // Jede TranslationSummary enthält language, path, published, targetPath.
    // published=false bleibt im Editor sichtbar, sofern lesbar.
    // tree->diagnostics enthält Fehler lesbarer Seiten; andere Seiten bleiben nutzbar.
    // Gesamte Website: $site->pages().
    return $tree;
};
