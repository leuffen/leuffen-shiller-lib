<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Ausnahmefall Permalink; Basisfixture de/en vorhanden, fr fehlt.
// Schiller-API noch nicht implementiert. Eine frische Arbeitskopie verwenden.

use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\SchillerDir;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): array {
    $site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
    $page = $site->getPage('/leistungen/diagnostik');
    $documents = [];
    foreach ($page->getTranslations() as $language => $info) {
        if (!$info->exists) {
            continue; // Keine fehlende Sprache anlegen.
        }
        $document = $page->getTranslation($language);
        $document->header['permalink'] = '/medizin/diagnostik/';
        $documents[] = $document;
    }

    // Sichtbar gruppenweiter Schreibauftrag; keine Schleife mit einzelnen save()-Aufrufen.
    // Erst den gemeinsamen Endzustand auf Rechte, Revisionen und Routen prüfen.
    // Unsichtbare/beschränkte Varianten sind kein Ausweg: unvollständige Gruppenumstellung ablehnen.
    $site->saveDocuments($documents);
    // Für HTTP-Formulare zusätzlich expectedRevisions in derselben Dokumentreihenfolge
    // aus der ursprünglichen GET-Antwort übergeben, analog Beispiel 18.

    return [
        'de' => $page->getUrl(),                       // '/medizin/diagnostik/'
        'en' => $page->getTranslation('en')->getUrl(), // '/en/medizin/diagnostik/'
        'frExists' => $page->getTranslations()['fr']->exists, // false
    ];
};

// Um wieder natürliche URLs zu verwenden: an allen vorhandenen Documents
// unset($document->header['permalink']) und diese gemeinsam mit saveDocuments speichern.
// ID und FileEntry bleiben gleich. published, Body und eigene Metadaten bleiben erhalten.
// Bei Fehler kein Teilerfolg; Adapter ohne sichere Batch-Fähigkeit lehnt vorher ab.
