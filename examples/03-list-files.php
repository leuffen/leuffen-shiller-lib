<?php

// Verwendet den lesenden $site aus 01; technische Ansicht der tatsächlichen Dateien.
$listing = $site->files('leistungen');
foreach ($listing->entries as $entry) {
    echo $entry->path; // leistungen/diagnostik.md
    $entry->file;     // FileEntry; bei einem Ordner null
    $entry->isLeaf(); // true für diese Datei
}

// Alternative für sämtliche Unterordner: ersetzt die vorherige Abfrage.
$listing = $site->files('leistungen', recursive: true);
// FileListing: entries=list<TreeNode>, diagnostics=[]; Ordner enthalten children.
// Nicht expandierte Ordner können trotz leerer children isLeaf()=false liefern.
