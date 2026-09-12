<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\NewTranslation;
use Leuffen\Schiller\PageDocument;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: nur eine Arbeitskopie übergeben; fr/leistungen/ muss bereits existieren.
return static function (PhoreDirectory $root): PageDocument {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $source = $site->page('leistungen/diagnostik.md');

    $created = $source->createTranslation(new NewTranslation(
        language: 'fr',
        title: 'Diagnostic',
        content: "## Diagnostic\n\nTexte français.\n",
    ));

    // PageDocument:
    // path='fr/leistungen/diagnostik.md', publication->value='unpublished'
    // header: title='Diagnostic', published=false; keine ID, kein lang, kein Permalink
    // Zielpfad aus Quellpfad und language automatisch bestimmt.
    // Eigene Metafelder können aus der Quelle übernommen werden.
    // Keine automatische Übersetzung, kein Commit/Deploy.
    // Erfordert read der Quelle und createFile + createTranslation am Ziel.
    // Wiederholung überschreibt nichts, sondern wirft AlreadyExistsException.
    return $created;
};
