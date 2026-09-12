<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Document;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: Arbeitskopie; fr/leistungen/ muss bereits existieren.
return static function (PhoreDirectory $root): Document {
    $site = new SchillerDir($root, access: new AccessContext(role: 'user'));
    $page = $site->getPage('leistungen/diagnostik.md');
    $french = $page->getTranslation('fr', createIfMissing: true);
    // Bei createIfMissing:true: Document oder Exception, niemals null.

    if (!$french->isPersisted()) {
        // Ungespeicherte Kopie des Stammdokuments:
        // path='fr/leistungen/diagnostik.md', language='fr', isRootDocument=false
        // header['title']='Diagnostik', header['published']=false
        // content ist zunächst der deutsche Body; keine automatische Übersetzung.
        $french->header['title'] = 'Diagnostic';
        $french->content = "## Diagnostic\n\nTexte français.\n";
        $french->save(); // Erst jetzt schreiben; isPersisted() liefert true.
    }

    // Bereits vorhandene Übersetzung bleibt unverändert.
    // Zielpfad automatisch, keine lang-/ID-Felder im Header.
    // Anlage braucht read der Quelle und createFile + createTranslation am Ziel.
    // Zwischenzeitlich angelegte Zieldateien werden nicht überschrieben.
    assert($french->getTranslation() === $page);
    assert($french->isPersisted());
    // Spätere ungespeicherte Inhaltsänderungen ändern isPersisted() nicht.
    return $french;
};
