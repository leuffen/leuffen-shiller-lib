<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\FrontMatter;
use Leuffen\Schiller\NewPage;
use Leuffen\Schiller\PageDocument;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: leistungen/ muss existieren, vorsorge.md darf nicht existieren.
return static function (PhoreDirectory $root): PageDocument {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $created = $site->createPage(new NewPage(
        path: 'leistungen/vorsorge.md',
        header: new FrontMatter([
            'title' => 'Vorsorge',
            'layout' => 'default',
        ]),
        content: "## Vorsorge\n",
    ));

    // PageDocument: path='leistungen/vorsorge.md',
    // publication->value='unpublished'; header->bool('published') === false
    // aus dem Anlage-Default in schiller.yaml.
    // Standard-URL: /leistungen/vorsorge.html; kein Permalink nötig.
    // Sprache de stammt aus der Root-Ablage, nicht aus dem Header.
    // Bestehende Dateien werden nicht überschrieben. Eine zusätzliche Sprache
    // einer bestehenden Gruppe muss die Übersetzungsrechte erfüllen (Beispiel 08).
    return $created;
};
