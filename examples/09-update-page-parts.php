<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\FrontMatterPatch;
use Leuffen\Schiller\PageDocument;
use Leuffen\Schiller\PagePatch;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: zeigt drei aufeinanderfolgende, getrennte Dateiänderungen.
return static function (PhoreDirectory $root): PageDocument {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $page = $site->page('leistungen/diagnostik.md');

    $headerChanged = $page->update(new PagePatch(
        header: new FrontMatterPatch(set: ['short_title' => 'Untersuchungen']),
    )); // PageDocument: neuer Kurztitel, Body unverändert

    $bodyChanged = $page->update(new PagePatch(
        content: "## Aktualisierte Diagnostik\n",
    )); // PageDocument: neuer Body, Header unverändert

    $removed = $page->update(new PagePatch(
        header: new FrontMatterPatch(remove: ['short_title']),
    )); // PageDocument: short_title fehlt, aktualisierter Body bleibt bestehen

    // content=null bedeutet unverändert, content='' leert den Body.
    // remove entfernt einen Schlüssel; set mit null speichert dagegen YAML-null,
    // sofern die Felddefinition null erlaubt. Geerbte Defaults werden nicht kopiert.
    return $removed;
};
