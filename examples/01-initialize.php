<?php

use Leuffen\Shiller\Access\Adapter\Adapter;
use Leuffen\Shiller\Access\Adapter\JekyllLegacyAdapter;
use Leuffen\Shiller\Access\Adapter\JekyllPolyglotAdapter;
use Leuffen\Shiller\Access\DTO\AccessContext;
use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\Access\Storage\SiteStorage;

// Standard: mitgelieferter JekyllPolyglotAdapter, sofern shiller.yaml keinen anderen auswählt.
$root = '/srv/site/docs';
$site = new ShillerRepository($root);
$config = $site->config();

// Alternative für alten Bestand: ersetzt die vorige Initialisierung und die YAML-Adapterauswahl.
$legacyRoot = '/srv/legacy-site/docs'; // Vorhandene PID-/Sprachdateien.
$legacy = new ShillerRepository($legacyRoot, adapter: new JekyllLegacyAdapter());

// Explizite Auswahl mit Bearbeitungsrechten; Rollen kommen aus der authentifizierten Anwendung.
$site = new ShillerRepository(
    $root,
    adapter: new JekyllPolyglotAdapter(),
    access: new AccessContext(role: 'user'),
);
// Kein Storage-Argument am Adapter: ShillerRepository ruft intern einmalig bind(SiteStorage) auf.
// Ein eigener Connector ersetzt $root durch eine SiteStorage-Implementierung.
// SiteStorage ist ein implementiertes Interface; ein Remote-Connector wird nicht mitgeliefert.
