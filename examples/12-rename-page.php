<?php

// Unabhängiger Schreibablauf; Rollenquelle ist die authentifizierte Anwendung.
$site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
$page = $site->getPage('/leistungen/diagnostik');
$page->rename('/medizin/diagnostik'); // Sofort: ganze Blattgruppe, kein save.
$page->id;         // '/medizin/diagnostik'
$page->file->path; // 'medizin/diagnostik.md'
$page->getTranslation('en')->file->path; // 'en/medizin/diagnostik.md'
// Explizite Permalinks bleiben erhalten; natürliche URLs folgen dem neuen Pfad.
// Übersetzungen dürfen nicht allein umbenannt werden; Aufruf am Original.
// Konflikt, fehlende Gruppenrechte oder lokale ungespeicherte Änderungen: keine Mutation.
