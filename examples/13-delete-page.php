<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\AccessContext;
use Phore\FileSystem\PhoreDirectory;

// SCHREIBBEISPIEL: Polyglot-Blattgruppe; Legacy lehnt delete ab.
// frische Arbeitskopie; löscht zuerst en, anschließend die Gruppe.
return static function (PhoreDirectory $root): void {
    $site = new SchillerDir($root, access: new AccessContext(role: 'admin'));
    $page = $site->getPage('/leistungen/diagnostik');
    $english = $page->getTranslation('en');

    $english->delete(); // Sofort: ausschließlich en/leistungen/diagnostik.md.
    assert($page->getTranslation('en') === null);
    assert($page->getTranslations()['en']->exists === false);
    // Root bleibt bestehen. createIfMissing:true könnte jetzt wieder eine Kopie vorbereiten.
    // $english ist ungültig und darf nicht per save() wiederhergestellt werden.

    $page->delete(); // Sofort: Root und sämtliche noch vorhandenen Übersetzungen.
    // getPage('/leistungen/diagnostik') wirft danach NotFoundException.
    // Fehlende Rechte auf einer Gruppendatei verhindern die gesamte Root-Löschung.
    // Kein implizites Speichern; ungespeicherte Änderungen führen zu einer Exception.

};
