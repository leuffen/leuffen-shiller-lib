<?php

// Standard: mitgelieferter JekyllPolyglotAdapter, sofern schiller.yaml keinen anderen auswählt.
$root = phore_dir('/srv/site/docs');
$site = new SchillerDir($root);
$config = $site->config();

// Alternative für alten Bestand: ersetzt die vorige Initialisierung und die YAML-Adapterauswahl.
$legacyRoot = phore_dir('/srv/legacy-site/docs'); // Vorhandene PID-/Sprachdateien.
$legacy = new SchillerDir($legacyRoot, adapter: new JekyllLegacyAdapter());

// Explizite Auswahl mit Bearbeitungsrechten; Rollen kommen aus der authentifizierten Anwendung.
$site = new SchillerDir(
    $root,
    adapter: new JekyllPolyglotAdapter(),
    access: new AccessContext(role: 'user'),
);
// Kein Storage-Argument am Adapter: SchillerDir ruft intern einmalig bind(SiteStorage) auf.
// Ein eigener Connector ersetzt $root durch eine SiteStorage-Implementierung.
// SiteStorage ist noch ein Vertragsentwurf (§ 11), kein mitgelieferter Remote-Connector.
