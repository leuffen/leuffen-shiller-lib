<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Document;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: drei getrennte Änderungen an einer Arbeitskopie.
return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $page = $site->getPage('/leistungen/diagnostik');

    $page->header['short_title'] = 'Untersuchungen';
    $page->header['custom_tracking'] = ['campaign' => 'sommer'];
    // Keine Definition nötig; eigene Metadaten bleiben auch beim nächsten save erhalten.
    $page->save(); // Kurztitel geändert, Body bytegenau erhalten.

    $page->content = "## Aktualisierte Diagnostik\n";
    $page->save(); // Body geändert, ursprünglicher Headerblock bleibt erhalten.

    unset($page->header['short_title']);
    $page->save(); // Feld entfernt, neuer Body bleibt bestehen.

    // header['key']=null speichert YAML-null, sofern erlaubt; unset entfernt.
    // content='' leert den Body. Änderungen werden erst mit save() geschrieben.
    // Geerbte Defaults werden nicht in header zurückgeschrieben.
    return $page;
};
