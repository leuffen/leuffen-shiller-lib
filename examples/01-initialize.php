<?php

// Alternative zum Einstieg: vorhandenes Quellverzeichnis nur lesen.
$root = phore_dir('/srv/site/docs');
$site = new SchillerDir($root); // reader; liest _config.yml und schiller.yaml selbst
$config = $site->config();

// Ersetzt die vorige Initialisierung für einen serverseitig authentifizierten Editor.
$site = new SchillerDir($root, access: new AccessContext(role: 'user'));

// Ein eigener Connector implementiert SiteStorage und wird an derselben Stelle übergeben:
// $site = new SchillerDir($storage, access: new AccessContext(role: 'user'));
// SiteStorage ist noch ein Vertragsentwurf (§ 11), kein mitgelieferter Remote-Connector.
// Vorhandene PhoreDirectory wird intern adaptiert; Git/Checkout erledigt die Anwendung.
