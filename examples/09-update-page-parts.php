<?php

// Unabhängiger Schreibablauf mit $site aus 00.
$page = $site->getPage('/leistungen/diagnostik');
$page->header['short_title'] = 'Untersuchungen';
$page->header['custom_tracking'] = ['campaign' => 'sommer'];
$page->save(); // Headeränderung; Body bleibt bytegenau erhalten.

$page->content = "## Aktualisierte Diagnostik\n";
$page->save(); // Bodyänderung; Headerblock bleibt erhalten.

unset($page->header['short_title']);
$page->save();
// short_title entfernt; custom_tracking und neuer Body bleiben erhalten.
// Ausgelassene Formularfelder bleiben erhalten. null speichert YAML-null; content='' leert den Body.
