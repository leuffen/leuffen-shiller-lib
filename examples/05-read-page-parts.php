<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\PageDocument;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): PageDocument {
    $site = new SchillerDir($root);
    $document = $site->page('leistungen/diagnostik.md')->read();

    $title = $document->header->string('title');             // ?string: 'Diagnostik'
    $published = $document->effectiveHeader->bool('published', true); // ?bool: true
    $content = $document->content;                          // string: Markdown-Body
    $stored = $document->header->all();                     // array<string, YamlValue>
    $effective = $document->effectiveHeader->all();          // ergänzt Jekyll-Defaults
    $layout = $document->effectiveHeader->string('layout');  // ?string: 'default'
    $origin = $document->origins['layout'];                 // ValueOrigin, ->value === 'jekyll_default'

    // header enthält weder page_id noch lang; effectiveHeader kann lang='de'
    // aus zentralen Jekyll-Defaults enthalten. Gruppen-ID kommt aus dem Dateipfad.
    // header enthält KEIN layout, wenn es nur aus _config.yml geerbt wurde.
    // content beginnt mit "## Diagnostik\n", ohne YAML-Header und ohne Liquid-Rendering.
    // Die gesamte Datei bleibt als PageDocument typisiert; zusätzliche YAML-Felder
    // sind bewusst dynamisch. Typfehler werden nicht stillschweigend konvertiert.
    return $document;
};
