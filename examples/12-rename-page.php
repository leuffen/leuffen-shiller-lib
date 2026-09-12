<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Document;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: Polyglot, frische Arbeitskopie; Ordner werden berechtigt angelegt.
// Die Host-Policy muss die Gruppenmutation ebenfalls erlauben.
return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
    $page = $site->getPage('/leistungen/diagnostik');
    $english = $page->getTranslation('en');

    $page->rename('/medizin/diagnostik'); // Sofortige Gruppenoperation.
    // $page->id === '/medizin/diagnostik'; $english->id ebenfalls.
    // $page->file->path === 'medizin/diagnostik.md'
    // $english->file->path === 'en/medizin/diagnostik.md'
    // $page->getUrl() === '/medizin/diagnostik.html'
    // $english->getUrl() === '/en/medizin/diagnostik.html'
    assert($english->getTranslation() === $page);

    // Kein rename() an der Übersetzung. Kein weiteres save() notwendig.
    // Vorab: alle Quellen/Ziele, Rechte, Kollisionen und Storage-Batchfähigkeit prüfen.
    // Ungespeicherte Änderungen => UnsavedChangesException.
    // Explizite Permalinks bleiben erhalten; diese Fixture hat keine.
    return $page;
};
