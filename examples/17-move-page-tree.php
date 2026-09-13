<?php

// Unabhängige Fixture: leistungen.md, en/leistungen.md als Ziel-Eltern;
// vorsorge/index.md, vorsorge/kinder.md, vorsorge/bild.png;
// en/vorsorge/index.md, en/vorsorge/kinder.md. fr fehlt.
$site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
$parent = $site->getPage('/leistungen');
$category = $site->getPage('/vorsorge');
$child = $site->getPage('/vorsorge/kinder');

$category->rename('/leistungen/vorsorge'); // Sofort: Zielpromotion und gesamter Teilbaum.
$parent->file->path;   // 'leistungen/index.md'; ID bleibt '/leistungen'
$category->id;         // '/leistungen/vorsorge'
$child->id;            // '/leistungen/vorsorge/kinder'
$child->getTranslation('en')->file->path; // 'en/leistungen/vorsorge/kinder.md'
// bild.png liegt jetzt unter leistungen/vorsorge/; keine Begleitdatei bleibt zurück.
// Vorhandenes Ziel, Ziel im eigenen Teilbaum oder Rechtefehler: keine Änderung.
// Für reine Kategorien ohne Document denselben Ablauf über site.rename(id, newId) aufrufen.
// Vollständiger Operations- und Testvertrag: docs/verschieben.md.
