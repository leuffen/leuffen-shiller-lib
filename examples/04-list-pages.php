<?php

// Verwendet den lesenden $site aus 01; Basisfixture mit Kategorie und einer Unterseite.
$tree = $site->pages('/leistungen');
$category = $tree->root;
$category->id;            // '/leistungen'
$category->label;         // 'leistungen': ohne Titel gilt das letzte ID-Segment
$category->data->file;    // null: Kategorie hat keine eigene Seite
$category->getDocument(); // null: nur aufklappen, keinen Seiteneditor öffnen
$category->isLeaf();      // false

$node = $category->children[0];
$node->id;                // '/leistungen/diagnostik'
$node->label;             // 'Diagnostik': title der bevorzugten lesbaren Seite
$node->data->file->path;  // 'leistungen/diagnostik.md'
$node->isLeaf();          // true; unabhängig davon, ob eine eigene Seite existiert

foreach ($node->data->translations as $language => $info) {
    echo $language;
    $info->exists;    // de=true, en=true, fr=false
    $info->published; // de=true, en=true, fr=null; kein Body-Laden nötig
}
$document = $node->getDocument(); // Document: id='/leistungen/diagnostik', language='de'
// Mit leistungen/index.md zeigt category.data.file auf diese Datei; Index ist kein zusätzliches Kind.
// data.metadata enthält ggf. Legacy-Section-Beschreibung/Formularhinweise.

// Für die Antwort eines bestehenden HTTP-Endpunkts:
$payload = $tree->toArray(); // root: {id, label, children, data}, diagnostics: []
$json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
// $json als application/json-Antwort senden; HTTP übernimmt die Anwendung.
// Frontend: [payload.root] direkt als Tree-Items; keine rekursive Umformung.
// Konvention v1 und Rendering-Beispiel: docs/tree-node.md.
