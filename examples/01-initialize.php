<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\AccessContext;
use Phore\FileSystem\PhoreDirectory;

// Keine Netzwerkverbindung: Ein extern bereitgestelltes docs/ ist der Einstieg.
return static function (PhoreDirectory $root): SchillerDir {
    $site = new SchillerDir($root); // reader, Konfiguration automatisch aus dem Root
    $config = $site->config();      // SiteConfig; kein manuelles Laden von YAML

    // Für einen bereits serverseitig authentifizierten Benutzer:
    $editor = new SchillerDir($root, access: new AccessContext(role: 'user'));
    // Nicht aus einem unkontrollierten Request-Parameter übernehmen.

    return $site; // SchillerDir; $editor zeigt alternativ die Initialisierung mit Rolle.
};
