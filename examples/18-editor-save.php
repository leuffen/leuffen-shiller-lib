<?php

// Zwei getrennte HTTP-Aktionen im Page Builder, keine nacheinander ausgeführte Demo.
// GET: verwendet den schreibenden $site aus 00; HTTP-Ausgabe übernimmt das Framework.
$page = $site->getPage('/leistungen/diagnostik');
$documentData = $page->toArray();
// JSON-fähiger Dokumentstand: id, language, header, content und opaker Bearbeitungszustand.
// Die UI bearbeitet header/content und erhält den übrigen Stand unverändert.

// POST: ersetzt den GET-Abschnitt; JSON-Body ist der komplette zurückgesendete Dokumentstand.
$documentData = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
$site = new SchillerDir($root, access: new AccessContext(role: 'user'));
try {
    $page = $site->restoreDocument($documentData);
    $page->save(); // Adapter prüft seinen mittransportierten Speicherstand selbst.
    $response = $page->toArray(); // Neuer Stand für die nächste Bearbeitung.
} catch (ConflictException $error) {
    // Anwendung antwortet z.B. mit HTTP 409 und zeigt „neu laden / Änderungen vergleichen“.
    // Kein automatischer Wiederholungsversuch mit einem frisch geladenen Speicherstand.
}
// Beispiel: GET liest A, anderer Editor speichert B, dieser POST scheitert ohne B zu überschreiben.
// Die UI verändert einzelne Headerkeys, statt das Array durch nur sichtbare Felder zu ersetzen.
// Entfernen ist ausdrücklich; fehlender Dokumentzustand wird abgewiesen, nicht neu erzeugt.
