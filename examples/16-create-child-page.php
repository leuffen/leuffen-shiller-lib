<?php

// Unabhängige Fixture: leistungen.md und en/leistungen.md, noch keine Indexdateien.
// Beide sind veröffentlicht; fr fehlt. Ersetzt die Basisfixture nur für diesen Ablauf.
$site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
$parent = $site->getPage('/leistungen');
$englishParent = $parent->getTranslation('en');
$child = $site->createPage(
    '/leistungen/allgemeinmedizin',
    header: ['title' => 'Allgemeinmedizin'],
    content: "## Allgemeinmedizin\n",
);
$parent->file->path; // 'leistungen.md': Vorbereitung hat noch nichts verschoben.

$child->save(); // Legt Kind an UND stellt vorhandene Elternsprachen gemeinsam auf Index um.
$parent->id;                // '/leistungen': unverändert
$parent->file->path;        // 'leistungen/index.md'
$englishParent->file->path; // 'en/leistungen/index.md'
$child->file->path;         // 'leistungen/allgemeinmedizin.md'
$parent->getUrl();          // '/leistungen/'; vorher '/leistungen.html'
// Eigene Elternmetadaten/Body bleiben erhalten; fr bleibt fehlend.
// Ungespeicherte betroffene Eltern oder fehlende Rechte verhindern die gesamte Operation.
