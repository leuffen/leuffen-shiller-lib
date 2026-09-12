<?php

declare(strict_types=1);

// ENTWURF: eigene Fixture, nicht der Ausgangsstand von Beispiel 04.
// Vorher: leistungen.md und en/leistungen.md, KEINE leistungen/index.md.
// Beide Eltern enthalten beliebige eigene Headerwerte und veröffentlichte Inhalte.
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Document;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
    $parent = $site->getPage('/leistungen');
    $englishParent = $parent->getTranslation('en');
    $headerBefore = $parent->header;
    $contentBefore = $parent->content;

    assert($parent->file->path === 'leistungen.md');
    $child = $site->createPage('/leistungen/allgemeinmedizin',
        header: ['title' => 'Allgemeinmedizin', 'custom_flag' => true],
        content: "## Allgemeinmedizin\n",
    );
    assert(!$child->isPersisted());
    assert($child->file === null);
    assert($parent->file->path === 'leistungen.md'); // Noch keine Umstellung.

    $child->save(); // Eine vorbereitete Operation inklusive Eltern und Sprachen.
    assert($parent->id === '/leistungen');
    assert($parent->file->path === 'leistungen/index.md');
    assert($englishParent->file->path === 'en/leistungen/index.md');
    assert($child->file->path === 'leistungen/allgemeinmedizin.md');
    assert($parent->header === $headerBefore);
    assert($parent->content === $contentBefore);
    assert($englishParent->getTranslation() === $parent);

    // Ohne Permalink: parent-URL vorher '/leistungen.html', jetzt '/leistungen/'.
    // Alte Dateien wurden verschoben, nicht zusätzlich kopiert.
    // fr fehlt weiterhin. Fehlende Kindübersetzungen bleiben ebenfalls fehlend.
    // Rechte/Kollisionen vorab prüfen; Details und spätere Tests: docs/verschieben.md.
    return $child;
};
