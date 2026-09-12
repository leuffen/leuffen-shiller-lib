<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\Document;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root);
    $document = $site->getPage('/leistungen/diagnostik');

    $title = $document->header['title'] ?? null; // 'Diagnostik'
    $content = $document->content;             // "## Diagnostik\n\nBeispielinhalt.\n"
    $stored = $document->header;               // array<string, YamlValue>
    $effective = $document->getEffectiveHeader();
    $layout = $effective['layout'] ?? null;     // 'default'
    $published = $effective['published'] ?? true; // true

    // id='/leistungen/diagnostik', file.path='leistungen/diagnostik.md', language='de', isRootDocument=true, isPersisted()=true
    // header enthält weder lang noch IDs; effective['lang']='de' aus Jekyll-Defaults.
    // layout steht nur im effektiven Header, nicht in den gespeicherten Werten.
    // Arraywerte bleiben dynamisch; save() validiert sie gegen die Felddefinitionen.
    return $document;
};
