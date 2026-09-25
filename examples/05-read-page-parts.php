<?php

// Verwendet den lesenden $site aus 01.
$page = $site->getPage('/leistungen/diagnostik');
$page->header['title']; // 'Diagnostik'
$page->content;         // "## Diagnostik\n\nBeispielinhalt.\n"
$page->file->path;      // 'leistungen/diagnostik.md'

$effective = $page->getEffectiveHeader();
$effective['layout'];  // 'default' aus _config.yml
$effective['lang'];    // 'de' aus Verzeichnis-Defaults
// header enthält nur eigene Werte. Effektive Defaults nicht als header zurückschreiben.
// Technischer adapterState gehört nicht zum YAML-Header; Anwendung wertet seine Schlüssel nicht aus.
