<?php

// Verwendet den lesenden $site aus 01; technische Ansicht der tatsächlichen Dateien.
$listing = $site->files('leistungen');
foreach ($listing->entries as $entry) {
    $entry->id;         // 'file:leistungen/diagnostik.md'; keine Seiten-ID
    $entry->label;      // 'diagnostik.md'
    echo $entry->data->path; // leistungen/diagnostik.md
    $entry->data->file; // FileEntry; bei einem Ordner null
    $entry->isLeaf();   // true für diese Datei
}

// Alternative für sämtliche Unterordner: ersetzt die vorherige Abfrage.
$listing = $site->files('leistungen', recursive: true);
$payload = $listing->toArray(); // entries: Liste aus id/label/children/data; diagnostics: []
// Frontend: payload.entries direkt als Tree-Items verwenden.
// Ohne recursive: Ordner mit lesbaren Kindern haben data.hasChildren=true,
// data.childrenLoaded=false und children=[]; nächste Ebene über files(data.path) laden.
