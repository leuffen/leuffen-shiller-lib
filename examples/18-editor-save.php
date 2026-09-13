<?php

// Zwei getrennte HTTP-Aktionen; GET verwendet den schreibenden $site aus 00.
$page = $site->getPage('/leistungen/diagnostik');
$documentData = $page->toArray();
// Die UI bearbeitet header/content und transportiert den übrigen Dokumentstand unverändert.
// adapterState darf leer sein; Versionsverwaltung erfolgt außerhalb von Schiller.

// POST: ersetzt den GET-Abschnitt; JSON-Body enthält den zurückgesendeten Dokumentstand.
$documentData = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
$site = new SchillerDir($root, access: new AccessContext(role: 'user'));
$page = $site->restoreDocument($documentData);
$page->save();
$response = $page->toArray(); // Gespeicherter Stand für die nächste Bearbeitung.
// Identität und Rechte bleiben serverseitig geprüft. Keine zugesagte Revisionsprüfung.
// ConflictException bleibt für einen späteren revisionsprüfenden Adapter vorgesehen;
// erst bei dessen Einführung braucht dieser Ablauf eine entsprechende Konfliktbehandlung.
