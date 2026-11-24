<?php

use Leuffen\Shiller\Access\DTO\AccessContext;
use Leuffen\Shiller\Access\ShillerRepository;

// /srv/site/.shiller.yml beginnt mit version: 1.0 und adapter: polyglot:1.0.
$root = '/srv/site/docs';
$site = new ShillerRepository($root, projectRoot: '/srv/site');
$config = $site->config();

// Alternative: /srv/legacy-site/.shiller.yml enthält adapter: legacy:1.0
// und version: 1.0. Der Adapter wird automatisch aus dem Mapping erzeugt.
$legacyRoot = '/srv/legacy-site/docs';
$legacy = new ShillerRepository($legacyRoot, projectRoot: '/srv/legacy-site');

// Mit Bearbeitungsrechten; Rollen kommen aus der authentifizierten Anwendung.
$site = new ShillerRepository(
    $root,
    access: new AccessContext(role: 'user'),
    projectRoot: '/srv/site',
);
// Ein eigener Connector ersetzt $root durch eine SiteStorage-Implementierung.
