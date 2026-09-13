<?php

// Vorhandene englische Seite bearbeiten; Ausgangsdaten und Rechte: README.
$site = new SchillerDir(
    phore_dir('/srv/site/docs'),
    access: new AccessContext(role: 'user'),
);
$page = $site->getPage('/leistungen/diagnostik');
$english = $page->getTranslation('en');
$english->header['title'] = 'Our diagnostics';
$english->save();
echo $english->getUrl(); // /en/leistungen/diagnostik.html
// Nur die englische Datei wurde gespeichert; der Adapter prüft ihren geladenen Stand.
