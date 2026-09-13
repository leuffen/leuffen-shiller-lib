<?php

// Verwendet den lesenden $site aus 01; Basisfixture mit Kategorie und einer Unterseite.
$tree = $site->pages('/leistungen');
$category = $tree->root;
$category->id;            // '/leistungen'
$category->file;          // null: Kategorie hat keine eigene Seite
$category->getDocument(); // null: nur aufklappen, keinen Editor öffnen
$category->isLeaf();      // false

$node = $category->children[0];
$node->id;                // '/leistungen/diagnostik'
$node->file->path;        // 'leistungen/diagnostik.md'
$node->isLeaf();          // true; unabhängig davon, ob eine eigene Seite existiert

foreach ($node->translations as $language => $info) {
    echo $language;
    $info->exists;    // de=true, en=true, fr=false
    $info->published; // de=true, en=true, fr=null; kein Body-Laden nötig
}
$document = $node->getDocument(); // Document: id='/leistungen/diagnostik', language='de'
// Mit leistungen/index.md zeigt category.file auf diese Datei; Index ist kein zusätzliches Kind.
// metadata enthält ggf. Legacy-Section-Beschreibung/Formularhinweise.
