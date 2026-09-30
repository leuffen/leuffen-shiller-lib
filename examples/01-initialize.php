<?php

// Standard: mitgelieferter JekyllPolyglotAdapter, sofern schiller.yaml keinen anderen auswählt.
$root = phore_dir('/srv/site/docs');
$site = new ShillerDir($root);
$config = $site->config();

// Alternative für alten Bestand: ersetzt die vorige Initialisierung und die YAML-Adapterauswahl.
$legacyRoot = phore_dir('/srv/legacy-site/docs'); // Vorhandene PID-/Sprachdateien.
$legacy = new ShillerDir($legacyRoot, adapter: new JekyllLegacyAdapter());

// Explizite Auswahl mit Bearbeitungsrechten; Rollen kommen aus der authentifizierten Anwendung.
$site = new ShillerDir(
    $root,
    adapter: new JekyllPolyglotAdapter(),
    access: new AccessContext(role: 'user'),
);
// Kein Storage-Argument am Adapter: ShillerDir ruft intern einmalig bind(SiteStorage) auf.
// Ein eigener Connector ersetzt $root durch eine SiteStorage-Implementierung.
// SiteStorage ist noch ein Vertragsentwurf (§ 11), kein mitgelieferter Remote-Connector.
