<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Document;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: leistungen/ muss existieren, vorsorge.md darf nicht existieren.
return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $page = $site->createPage(
        'leistungen/vorsorge.md',
        header: ['title' => 'Vorsorge', 'published' => false],
        content: "## Vorsorge\n",
    );

    // Document: path='leistungen/vorsorge.md', language='de',
    // isRootDocument=true, isPersisted()=false. Noch keine Datei angelegt.
    assert($page->getTranslation() === $page);
    assert(!$page->isPersisted());
    $page->save(); // isPersisted()=true; kein Überschreiben bestehender Dateien.
    assert($page->isPersisted());
    // getUrl() === '/leistungen/vorsorge.html'; keine Sprach-ID, kein Permalink.
    return $page;
};
